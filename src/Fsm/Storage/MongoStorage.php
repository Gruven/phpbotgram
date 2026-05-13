<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Storage;

use function Amp\async;

use Gruven\PhpBotGram\Fsm\State;
use MongoDB\Client;

/**
 * MongoDB-backed FSM storage.
 *
 * Mirrors `aiogram.fsm.storage.mongo.MongoStorage` (≈170 lines).
 * State and data are stored as fields inside a single MongoDB document
 * keyed by `_id = keyBuilder->build($key)` (no part suffix — one document
 * per FSM context, unlike Redis which uses separate keys per part).
 *
 * # I/O model
 *
 * The `mongodb/mongodb` userland library is **synchronous** (blocking I/O).
 * Every blocking call is wrapped in `Amp\async(fn)` so the blocking work
 * runs in a separate fiber and the parent fiber is suspended, not stalled.
 * This mirrors the pattern established in `BaseSession`.
 *
 * # Dependency injection
 *
 * The constructor accepts a `MongoCollectionInterface` — a tiny internal
 * contract satisfied by `MongoCollectionAdapter` (which wraps the real
 * `\MongoDB\Collection`) in production, and by lightweight anonymous-class
 * mocks in unit tests.  This decouples the storage from `ext-mongodb` at
 * the type level, so the unit-test suite runs without the extension.
 *
 * # Factory methods
 *
 * - `MongoStorage::fromUrl(string $url, ...)` — builds a real
 *   `\MongoDB\Client`, wraps the selected collection in an adapter.
 *   Requires `ext-mongodb`.
 * - `MongoStorage::create(\MongoDB\Client $client, ...)` — same but
 *   accepts a pre-built client (useful when TLS / auth options are complex).
 * - `MongoStorage::fromCollection(MongoCollectionInterface $col, ...)` —
 *   primary injection point; used by unit tests.
 *
 * # Document structure
 *
 * ```json
 * { "_id": "<keyBuilder_output>", "state": "Group:name", "data": { ... } }
 * ```
 *
 * When `state` / `data` is cleared, `$unset` removes the field.  If both
 * fields are absent after the update the document is deleted to avoid
 * accumulating empty records (upstream Python behaviour).
 *
 * @see MongoCollectionInterface
 * @see MongoCollectionAdapter
 */
final class MongoStorage extends BaseStorage
{
  /**
   * @param MongoCollectionInterface $collection Adapted MongoDB collection.
   * @param KeyBuilder $keyBuilder Key-builder strategy.
   */
  public function __construct(
    private readonly MongoCollectionInterface $collection,
    private readonly KeyBuilder $keyBuilder = new DefaultKeyBuilder(),
  ) {}

  // ------------------------------------------------------------------ //
  // Static factories
  // ------------------------------------------------------------------ //

  /**
   * Create a `MongoStorage` from a MongoDB connection URI.
   *
   * Mirrors `MongoStorage.from_url` (upstream Python). Requires `ext-mongodb`.
   *
   * @param string $url MongoDB connection URI.
   * @param array<string, mixed> $clientOptions Options forwarded to `\MongoDB\Client`.
   * @param array<string, mixed> $driverOptions Driver options forwarded to `\MongoDB\Client`.
   * @param string $database Database name.
   * @param string $collectionName Collection name.
   * @param null|KeyBuilder $keyBuilder Optional key-builder override.
   */
  public static function fromUrl(
    string $url,
    array $clientOptions = [],
    array $driverOptions = [],
    string $database = 'phpbotgram_fsm',
    string $collectionName = 'states_and_data',
    ?KeyBuilder $keyBuilder = null,
  ): self {
    $client = new Client($url, $clientOptions, $driverOptions);

    return self::create($client, $database, $collectionName, $keyBuilder);
  }

  /**
   * Create a `MongoStorage` from an existing `\MongoDB\Client`.
   *
   * @param Client $client Pre-built MongoDB client.
   * @param string $database Database name.
   * @param string $collectionName Collection name.
   * @param null|KeyBuilder $keyBuilder Optional key-builder override.
   */
  public static function create(
    Client $client,
    string $database = 'phpbotgram_fsm',
    string $collectionName = 'states_and_data',
    ?KeyBuilder $keyBuilder = null,
  ): self {
    $collection = new MongoCollectionAdapter(
      $client->selectCollection($database, $collectionName),
    );

    return new self(
      collection: $collection,
      keyBuilder: $keyBuilder ?? new DefaultKeyBuilder(),
    );
  }

  /**
   * Create a `MongoStorage` from a `MongoCollectionInterface` directly.
   *
   * This is the primary injection point used by unit tests.
   *
   * @param MongoCollectionInterface $collection Adapted (or mocked) collection.
   * @param null|KeyBuilder $keyBuilder Optional key-builder override.
   */
  public static function fromCollection(
    MongoCollectionInterface $collection,
    ?KeyBuilder $keyBuilder = null,
  ): self {
    return new self(
      collection: $collection,
      keyBuilder: $keyBuilder ?? new DefaultKeyBuilder(),
    );
  }

  // ------------------------------------------------------------------ //
  // BaseStorage implementation
  // ------------------------------------------------------------------ //

  /**
   * Persist the FSM state for the given key.
   *
   * - `$state === null` → `$unset` the `state` field; if the document is
   *   now empty (no `data` field either), delete the document.
   * - `$state instanceof State` → store `$state->state()`.
   * - `$state` is a plain string → store as-is.
   *
   * Every blocking MongoDB call is wrapped in `Amp\async()` so the
   * event loop is not stalled.
   *
   * @param StorageKey $key Storage address.
   * @param null|State|string $state New state value.
   */
  public function setState(StorageKey $key, null|State|string $state = null): void
  {
    $documentId = $this->keyBuilder->build($key);

    async(function () use ($documentId, $state): void {
      if ($state === null) {
        $this->unsetField($documentId, 'state');

        return;
      }

      if ($state instanceof State) {
        $stateString = $state->state() ?? '';
      } else {
        $stateString = $state;
      }

      $this->collection->updateOne(
        ['_id' => $documentId],
        ['$set' => ['state' => $stateString]],
        ['upsert' => true],
      );
    })->await();
  }

  /**
   * Retrieve the FSM state for the given key.
   *
   * @param StorageKey $key Storage address.
   *
   * @return null|string Stored state name, or `null` if none.
   */
  public function getState(StorageKey $key): ?string
  {
    $documentId = $this->keyBuilder->build($key);

    /** @var ?string $result */
    $result = async(function () use ($documentId): ?string {
      $document = $this->collection->findOne(['_id' => $documentId]);

      if ($document === null) {
        return null;
      }

      $state = $document->state ?? null;

      return $state === null ? null : (string)$state;
    })->await();

    return $result;
  }

  /**
   * Persist the FSM data payload for the given key.
   *
   * An empty array results in the `data` field being `$unset`.  If the
   * document becomes entirely empty afterward it is deleted.
   *
   * @param StorageKey $key Storage address.
   * @param array<string, mixed> $data Data map to store.
   */
  public function setData(StorageKey $key, array $data): void
  {
    $documentId = $this->keyBuilder->build($key);

    async(function () use ($documentId, $data): void {
      if ($data === []) {
        $this->unsetField($documentId, 'data');

        return;
      }

      $this->collection->updateOne(
        ['_id' => $documentId],
        ['$set' => ['data' => $data]],
        ['upsert' => true],
      );
    })->await();
  }

  /**
   * Retrieve the FSM data payload for the given key.
   *
   * @param StorageKey $key Storage address.
   *
   * @return array<string, mixed> Current data map (empty array when no data stored).
   */
  public function getData(StorageKey $key): array
  {
    $documentId = $this->keyBuilder->build($key);

    /** @var array<string, mixed> $result */
    $result = async(function () use ($documentId): array {
      $document = $this->collection->findOne(['_id' => $documentId]);

      if ($document === null) {
        return [];
      }

      /** @var null|array<string, mixed> $data */
      $data = isset($document->data) ? (array)$document->data : null;

      return $data ?? [];
    })->await();

    return $result;
  }

  /**
   * Merge `$data` into the existing FSM data payload for `$key` atomically.
   *
   * Unlike the default `BaseStorage::updateData` (read-merge-write, racy
   * under concurrency), this override uses a single `updateOne` with
   * dot-notation `$set` — matching upstream's atomic
   * `findOneAndUpdate($set: {data.field: value, ...}, upsert=True)` pattern
   * (`aiogram/fsm/storage/mongo.py:133-146`).
   *
   * When `$data` is empty the operation is skipped and the current data is
   * returned unchanged.
   *
   * @param StorageKey $key Storage address.
   * @param array<string, mixed> $data Partial data map to merge in.
   *
   * @return array<string, mixed> The merged data map as it now exists in the document.
   */
  public function updateData(StorageKey $key, array $data): array
  {
    if ($data === []) {
      return $this->getData($key);
    }

    $documentId = $this->keyBuilder->build($key);

    $setDoc = [];

    foreach ($data as $field => $value) {
      $setDoc["data.{$field}"] = $value;
    }

    async(function () use ($documentId, $setDoc): void {
      $this->collection->updateOne(
        ['_id' => $documentId],
        ['$set' => $setDoc],
        ['upsert' => true],
      );
    })->await();

    return $this->getData($key);
  }

  /**
   * Release resources.
   *
   * `\MongoDB\Client` manages an internal connection pool and does not
   * expose an explicit `close()` method.  This is intentionally a no-op,
   * matching upstream Python's `self._client.close()` which is also
   * effectively a no-op in most deployments.
   */
  public function close(): void
  {
    // Intentional no-op: the mongodb/mongodb library manages its own
    // connection pool and does not require explicit teardown.
  }

  // ------------------------------------------------------------------ //
  // Private helpers
  // ------------------------------------------------------------------ //

  /**
   * Remove `$field` from the document identified by `$documentId` using
   * `$unset`.  If the document is empty after the update (or absent), the
   * whole document is deleted to avoid accumulating empty records.
   *
   * Must be called from within an `Amp\async()` closure.
   */
  private function unsetField(string $documentId, string $field): void
  {
    $result = $this->collection->findOneAndUpdate(
      ['_id' => $documentId],
      ['$unset' => [$field => 1]],
      ['projection' => ['_id' => 0]],
    );

    if ($result === null || empty((array)$result)) {
      $this->collection->deleteOne(['_id' => $documentId]);
    }
  }
}
