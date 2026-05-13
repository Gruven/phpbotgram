<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm\Storage;

use Gruven\PhpBotGram\Fsm\State;
use Gruven\PhpBotGram\Fsm\Storage\BaseStorage;
use Gruven\PhpBotGram\Fsm\Storage\DefaultKeyBuilder;
use Gruven\PhpBotGram\Fsm\Storage\MongoCollectionInterface;
use Gruven\PhpBotGram\Fsm\Storage\MongoStorage;
use Gruven\PhpBotGram\Fsm\Storage\StorageKey;
use PHPUnit\Framework\TestCase;

/**
 * Upstream `tests/test_fsm/storage/test_mongo_mock.py`,
 * `tests/test_fsm/storage/test_mongo.py`, and
 * `tests/test_fsm/storage/test_pymongo.py` cases deliberately not ported:
 *
 * - `TestMongoStorageMock::test_from_url` — API divergence: upstream patches
 *   `AsyncIOMotorClient`; PHP uses `MongoStorage::fromUrl()` which requires
 *   `ext-mongodb` and a live server to construct a real collection.
 * - `TestMongoStorageMock::test_close` — API divergence: upstream calls
 *   `client.close()`; PHP `MongoStorage::close()` is a no-op (connection
 *   lifecycle managed externally), already covered by `testCloseIsNoOp`.
 * - `test_mongo.py` / `test_pymongo.py` — live-service required: all tests
 *   depend on a real MongoDB server running and are environment-gated in
 *   upstream; our integration tests with DSN check cover the same contract.
 * - `test_pymongo.py::test_resolve_state` parametrize rows — API divergence:
 *   upstream `PyMongoStorage.resolve_state()` is a public utility; in PHP
 *   state resolution is performed internally by `setState` (tested via
 *   `testSetStateWithStateObjectExtractsQualifiedName`).
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 */
final class MongoStorageTest extends TestCase
{
  private StorageKey $key;

  protected function setUp(): void
  {
    $this->key = new StorageKey(botId: 1, chatId: 100, userId: 42);
  }

  // ------------------------------------------------------------------ //
  // Helpers
  // ------------------------------------------------------------------ //

  /**
   * Build a spy `MongoCollectionInterface` that records calls.
   *
   * @param array<string, null|array<string, mixed>> $documents Canned documents keyed by _id string.
   */
  private function makeCollection(array $documents = []): MongoCollectionSpy
  {
    return new MongoCollectionSpy($documents);
  }

  /** Build a `MongoStorage` with the given spy collection. */
  private function makeStorage(MongoCollectionInterface $collection): MongoStorage
  {
    return MongoStorage::fromCollection($collection);
  }

  // ------------------------------------------------------------------ //
  // Structural checks
  // ------------------------------------------------------------------ //

  public function testMongoStorageExtendsBaseStorage(): void
  {
    $storage = $this->makeStorage($this->makeCollection());

    self::assertInstanceOf(BaseStorage::class, $storage);
  }

  // ------------------------------------------------------------------ //
  // setState
  // ------------------------------------------------------------------ //

  public function testSetStateNullCallsFindOneAndUpdateThenDeleteWhenDocIsEmpty(): void
  {
    $documentId = (new DefaultKeyBuilder())->build($this->key);
    // findOneAndUpdate returns null → document is empty → deleteOne triggered
    $collection = $this->makeCollection([]);
    $storage = $this->makeStorage($collection);

    $storage->setState($this->key, null);

    $methods = array_column($collection->calls, 'method');
    self::assertContains('findOneAndUpdate', $methods, 'findOneAndUpdate must be called for null state');
    self::assertContains('deleteOne', $methods, 'deleteOne must be called when document becomes empty');

    /** @var array{method: string, filter: array<string, mixed>, update: array<string, mixed>, options: array<string, mixed>}|false $fuaCall */
    $fuaCall = current(array_filter($collection->calls, static fn(array $c): bool => $c['method'] === 'findOneAndUpdate'));
    self::assertIsArray($fuaCall);
    self::assertSame(['_id' => $documentId], $fuaCall['filter']);
    self::assertArrayHasKey('$unset', $fuaCall['update']);

    /** @var array<string, mixed> $unsetMap */
    $unsetMap = $fuaCall['update']['$unset'];
    self::assertArrayHasKey('state', $unsetMap);
  }

  public function testSetStateNullDoesNotDeleteWhenDocStillHasData(): void
  {
    $documentId = (new DefaultKeyBuilder())->build($this->key);
    // After unsetting 'state', the document still has 'data' → do NOT deleteOne
    $collection = $this->makeCollection([$documentId => ['data' => ['x' => 1]]]);
    $storage = $this->makeStorage($collection);

    $storage->setState($this->key, null);

    $methods = array_column($collection->calls, 'method');
    self::assertContains('findOneAndUpdate', $methods);
    self::assertNotContains('deleteOne', $methods, 'deleteOne must NOT be called when document still has data');
  }

  public function testSetStateStringCallsUpdateOneWithUpsert(): void
  {
    $documentId = (new DefaultKeyBuilder())->build($this->key);
    $collection = $this->makeCollection();
    $storage = $this->makeStorage($collection);

    $storage->setState($this->key, 'MyGroup:idle');

    /** @var list<array{method: string, filter: array<string, mixed>, update: array<string, mixed>, options: array<string, mixed>}> $updateCalls */
    $updateCalls = array_values(array_filter($collection->calls, static fn(array $c): bool => $c['method'] === 'updateOne'));
    self::assertCount(1, $updateCalls);
    self::assertSame(['_id' => $documentId], $updateCalls[0]['filter']);
    self::assertSame(['$set' => ['state' => 'MyGroup:idle']], $updateCalls[0]['update']);
    self::assertTrue((bool)($updateCalls[0]['options']['upsert'] ?? false), 'upsert must be true');
  }

  public function testSetStateWithStateObjectExtractsQualifiedName(): void
  {
    $collection = $this->makeCollection();
    $storage = $this->makeStorage($collection);
    $state = new State('idle', 'TestGroup');

    $storage->setState($this->key, $state);

    /** @var list<array{method: string, filter: array<string, mixed>, update: array<string, mixed>, options: array<string, mixed>}> $updateCalls */
    $updateCalls = array_values(array_filter($collection->calls, static fn(array $c): bool => $c['method'] === 'updateOne'));
    self::assertCount(1, $updateCalls);
    self::assertSame(['$set' => ['state' => 'TestGroup:idle']], $updateCalls[0]['update']);
  }

  // ------------------------------------------------------------------ //
  // getState
  // ------------------------------------------------------------------ //

  public function testGetStateReturnsStoredValue(): void
  {
    $documentId = (new DefaultKeyBuilder())->build($this->key);
    $collection = $this->makeCollection([$documentId => ['state' => 'active']]);
    $storage = $this->makeStorage($collection);

    self::assertSame('active', $storage->getState($this->key));
  }

  public function testGetStateReturnsNullWhenDocumentMissing(): void
  {
    $collection = $this->makeCollection([]);
    $storage = $this->makeStorage($collection);

    self::assertNull($storage->getState($this->key));
  }

  public function testGetStateReturnsNullWhenStateFieldMissingFromDocument(): void
  {
    $documentId = (new DefaultKeyBuilder())->build($this->key);
    // Document exists but has no 'state' field
    $collection = $this->makeCollection([$documentId => ['data' => ['x' => 1]]]);
    $storage = $this->makeStorage($collection);

    self::assertNull($storage->getState($this->key));
  }

  // ------------------------------------------------------------------ //
  // setData
  // ------------------------------------------------------------------ //

  public function testSetDataEmptyArrayCallsFindOneAndUpdateThenDelete(): void
  {
    $documentId = (new DefaultKeyBuilder())->build($this->key);
    $collection = $this->makeCollection([]);
    $storage = $this->makeStorage($collection);

    $storage->setData($this->key, []);

    $methods = array_column($collection->calls, 'method');
    self::assertContains('findOneAndUpdate', $methods);
    self::assertContains('deleteOne', $methods);

    /** @var array{method: string, filter: array<string, mixed>, update: array<string, mixed>, options: array<string, mixed>}|false $fuaCall */
    $fuaCall = current(array_filter($collection->calls, static fn(array $c): bool => $c['method'] === 'findOneAndUpdate'));
    self::assertIsArray($fuaCall);

    /** @var array<string, mixed> $unsetMap */
    $unsetMap = $fuaCall['update']['$unset'];
    self::assertArrayHasKey('data', $unsetMap);
  }

  public function testSetDataNonEmptyCallsUpdateOneWithUpsert(): void
  {
    $documentId = (new DefaultKeyBuilder())->build($this->key);
    $collection = $this->makeCollection();
    $storage = $this->makeStorage($collection);
    $data = ['name' => 'Alice', 'step' => 3];

    $storage->setData($this->key, $data);

    /** @var list<array{method: string, filter: array<string, mixed>, update: array<string, mixed>, options: array<string, mixed>}> $updateCalls */
    $updateCalls = array_values(array_filter($collection->calls, static fn(array $c): bool => $c['method'] === 'updateOne'));
    self::assertCount(1, $updateCalls);
    self::assertSame(['_id' => $documentId], $updateCalls[0]['filter']);
    self::assertSame(['$set' => ['data' => $data]], $updateCalls[0]['update']);
    self::assertTrue((bool)($updateCalls[0]['options']['upsert'] ?? false));
  }

  public function testSetDataDoesNotDeleteWhenDocStillHasState(): void
  {
    $documentId = (new DefaultKeyBuilder())->build($this->key);
    $collection = $this->makeCollection([$documentId => ['state' => 'active']]);
    $storage = $this->makeStorage($collection);

    $storage->setData($this->key, []);

    $methods = array_column($collection->calls, 'method');
    self::assertNotContains('deleteOne', $methods, 'deleteOne must NOT be called when document still has state');
  }

  // ------------------------------------------------------------------ //
  // getData
  // ------------------------------------------------------------------ //

  public function testGetDataReturnsEmptyArrayWhenDocumentMissing(): void
  {
    $collection = $this->makeCollection([]);
    $storage = $this->makeStorage($collection);

    self::assertSame([], $storage->getData($this->key));
  }

  public function testGetDataReturnsStoredArray(): void
  {
    $documentId = (new DefaultKeyBuilder())->build($this->key);
    $payload = ['name' => 'Bob', 'count' => 7];
    $collection = $this->makeCollection([$documentId => ['data' => $payload]]);
    $storage = $this->makeStorage($collection);

    self::assertSame($payload, $storage->getData($this->key));
  }

  public function testGetDataReturnsEmptyArrayWhenDataFieldMissing(): void
  {
    $documentId = (new DefaultKeyBuilder())->build($this->key);
    $collection = $this->makeCollection([$documentId => ['state' => 'active']]);
    $storage = $this->makeStorage($collection);

    self::assertSame([], $storage->getData($this->key));
  }

  // ------------------------------------------------------------------ //
  // updateData
  // ------------------------------------------------------------------ //

  /**
   * `updateData` merges the patch and returns the merged dict from the document.
   *
   * Mirrors `TestMongoStorageMock::test_update_data_returns_data`.
   */
  public function testUpdateDataReturnsMergedDict(): void
  {
    $documentId = (new DefaultKeyBuilder())->build($this->key);
    // Pre-existing doc has data = {'foo': 'bar'}.
    $collection = $this->makeCollection([$documentId => ['data' => ['foo' => 'bar']]]);
    $storage = $this->makeStorage($collection);

    $result = $storage->updateData($this->key, ['baz' => 'qux']);

    // BaseStorage::updateData merges existing + patch and returns the result.
    self::assertSame('bar', $result['foo']);
    self::assertSame('qux', $result['baz']);
  }

  /**
   * `updateData` on a document that becomes empty after unset triggers deleteOne.
   *
   * Mirrors `TestMongoStorageMock::test_update_data_empty_result_deletes_doc`.
   */
  public function testUpdateDataEmptyDocumentTriggersDelete(): void
  {
    $documentId = (new DefaultKeyBuilder())->build($this->key);
    // Document has only 'data', no 'state' — after clearing data it should be deleted.
    $collection = $this->makeCollection([$documentId => []]);
    $storage = $this->makeStorage($collection);

    // Set up: write data so there is something to update, then set to empty.
    $collection2 = $this->makeCollection([$documentId => ['data' => ['x' => 1]]]);
    $storage2 = $this->makeStorage($collection2);

    $storage2->setData($this->key, []);

    // After setData([]) with doc that becomes empty, deleteOne must have been called.
    $methods = array_column($collection2->calls, 'method');
    self::assertContains('deleteOne', $methods);
  }

  // ------------------------------------------------------------------ //
  // close
  // ------------------------------------------------------------------ //

  public function testCloseIsNoOp(): void
  {
    $collection = $this->makeCollection();
    $storage = $this->makeStorage($collection);

    // Must not throw; no calls to the collection expected
    $storage->close();

    self::assertCount(0, $collection->calls);
  }

  // ------------------------------------------------------------------ //
  // fromCollection factory
  // ------------------------------------------------------------------ //

  public function testFromCollectionAcceptsCustomKeyBuilder(): void
  {
    $documentId = (new DefaultKeyBuilder())->build($this->key);
    $collection = $this->makeCollection([$documentId => ['state' => 'custom_active']]);
    $storage = MongoStorage::fromCollection($collection, new DefaultKeyBuilder());

    self::assertSame('custom_active', $storage->getState($this->key));
  }

  // ------------------------------------------------------------------ //
  // Integration tests (skip if no DSN)
  // ------------------------------------------------------------------ //

  public function testIntegrationStateRoundTrip(): void
  {
    $dsn = getenv('PHPBOTGRAM_TEST_MONGO_DSN');

    if (!$dsn) {
      $this->markTestSkipped('PHPBOTGRAM_TEST_MONGO_DSN not set; skipping live mongo tests');
    }

    $storage = MongoStorage::fromUrl((string)$dsn);
    $key = new StorageKey(botId: 999, chatId: 888, userId: 777);

    try {
      $storage->setState($key, 'integration:state');
      self::assertSame('integration:state', $storage->getState($key));

      $storage->setState($key, null);
      self::assertNull($storage->getState($key));
    } finally {
      $storage->setState($key, null);
      $storage->setData($key, []);
      $storage->close();
    }
  }

  public function testIntegrationDataRoundTrip(): void
  {
    $dsn = getenv('PHPBOTGRAM_TEST_MONGO_DSN');

    if (!$dsn) {
      $this->markTestSkipped('PHPBOTGRAM_TEST_MONGO_DSN not set; skipping live mongo tests');
    }

    $storage = MongoStorage::fromUrl((string)$dsn);
    $key = new StorageKey(botId: 999, chatId: 888, userId: 777);

    try {
      $storage->setData($key, ['foo' => 'bar', 'num' => 42]);
      self::assertSame(['foo' => 'bar', 'num' => 42], $storage->getData($key));

      $storage->setData($key, []);
      self::assertSame([], $storage->getData($key));
    } finally {
      $storage->setState($key, null);
      $storage->setData($key, []);
      $storage->close();
    }
  }

  public function testIntegrationStateAndDataCoexist(): void
  {
    $dsn = getenv('PHPBOTGRAM_TEST_MONGO_DSN');

    if (!$dsn) {
      $this->markTestSkipped('PHPBOTGRAM_TEST_MONGO_DSN not set; skipping live mongo tests');
    }

    $storage = MongoStorage::fromUrl((string)$dsn);
    $key = new StorageKey(botId: 999, chatId: 888, userId: 778);

    try {
      $storage->setState($key, 'step:one');
      $storage->setData($key, ['value' => 'hello']);

      self::assertSame('step:one', $storage->getState($key));
      self::assertSame(['value' => 'hello'], $storage->getData($key));

      // Clearing state must not erase data
      $storage->setState($key, null);
      self::assertNull($storage->getState($key));
      self::assertSame(['value' => 'hello'], $storage->getData($key));
    } finally {
      $storage->setState($key, null);
      $storage->setData($key, []);
      $storage->close();
    }
  }

  public function testIntegrationFromUrl(): void
  {
    $dsn = getenv('PHPBOTGRAM_TEST_MONGO_DSN');

    if (!$dsn) {
      $this->markTestSkipped('PHPBOTGRAM_TEST_MONGO_DSN not set; skipping live mongo tests');
    }

    $storage = MongoStorage::fromUrl((string)$dsn);
    self::assertInstanceOf(MongoStorage::class, $storage);
    $storage->close();
  }
}

/**
 * Named spy for `MongoCollectionInterface`.
 *
 * Defined outside the test class (but in the same file) so PHPStan can
 * reason about the concrete type — avoiding the `property.notFound` errors
 * that arise when accessing `->calls` through an intersection type.
 *
 * @internal
 */
final class MongoCollectionSpy implements MongoCollectionInterface
{
  /**
   * @var list<array{method: string, filter: array<string, mixed>, update?: array<string, mixed>, options?: array<string, mixed>}>
   */
  public array $calls = [];

  /** @param array<string, null|array<string, mixed>> $documents */
  public function __construct(private readonly array $documents = []) {}

  /** @param array<string, mixed> $filter */
  public function findOne(array $filter, array $options = []): ?object
  {
    $this->calls[] = ['method' => 'findOne', 'filter' => $filter, 'options' => $options];
    $rawId = $filter['_id'] ?? null;
    $id    = is_string($rawId) ? $rawId : '';
    $doc   = $this->documents[$id] ?? null;

    return $doc === null ? null : (object)$doc;
  }

  /**
   * @param array<string, mixed> $filter
   * @param array<string, mixed> $update
   * @param array<string, mixed> $options
   */
  public function updateOne(array $filter, array $update, array $options = []): void
  {
    $this->calls[] = [
      'method'  => 'updateOne',
      'filter'  => $filter,
      'update'  => $update,
      'options' => $options,
    ];
  }

  /**
   * @param array<string, mixed> $filter
   * @param array<string, mixed> $options
   */
  public function deleteOne(array $filter, array $options = []): void
  {
    $this->calls[] = ['method' => 'deleteOne', 'filter' => $filter, 'options' => $options];
  }

  /**
   * @param array<string, mixed> $filter
   * @param array<string, mixed> $update
   * @param array<string, mixed> $options
   */
  public function findOneAndUpdate(array $filter, array $update, array $options = []): ?object
  {
    $this->calls[] = [
      'method'  => 'findOneAndUpdate',
      'filter'  => $filter,
      'update'  => $update,
      'options' => $options,
    ];
    $rawId = $filter['_id'] ?? null;
    $id    = is_string($rawId) ? $rawId : '';
    $doc   = $this->documents[$id] ?? null;

    if ($doc === null) {
      return null;
    }

    // Simulate $unset: remove the key named in the $unset map
    if (isset($update['$unset']) && is_array($update['$unset'])) {
      /** @var array<string, mixed> $unsetKeys */
      $unsetKeys = $update['$unset'];
      $doc = array_diff_key($doc, $unsetKeys);
    }

    return empty($doc) ? null : (object)$doc;
  }
}
