<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm\Storage;

use Gruven\PhpBotGram\Fsm\Storage\BaseStorage;
use Gruven\PhpBotGram\Fsm\Storage\StorageKey;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Upstream `tests/test_fsm/storage/test_storages.py` cases deliberately
 * not ported here:
 *
 * - `TestStorages::test_set_state` parametrize rows `redis_storage`,
 *   `mongo_storage`, `pymongo_storage` — live-service required: need real
 *   Redis / MongoDB; memory_storage path covered here; others covered by
 *   integration tests in `RedisStorageTest` / `MongoStorageTest`.
 * - `TestStorages::test_set_data` with `TypedDict` and invalid data type —
 *   API divergence: PHP has no `TypedDict` equivalent; dict-like validation
 *   differs; invalid data type check is covered separately by the upstream
 *   `DataNotDictLikeError` contract in each storage test.
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 *
 * @internal
 */
final class BaseStorageTest extends TestCase
{
  private BaseStorage $storage;
  private StorageKey $key;

  protected function setUp(): void
  {
    $this->storage = new InMemoryStorage();
    $this->key = new StorageKey(botId: 1, chatId: 100, userId: 42);
  }

  // ------------------------------------------------------------------ //
  // Abstract-method surface
  // ------------------------------------------------------------------ //

  /**
   * The class itself must be abstract.
   */
  public function testBaseStorageIsAbstract(): void
  {
    $rc = new ReflectionClass(BaseStorage::class);

    self::assertTrue($rc->isAbstract());
  }

  /**
   * `setState` must be declared abstract.
   */
  public function testSetStateIsAbstract(): void
  {
    $rm = new ReflectionMethod(BaseStorage::class, 'setState');

    self::assertTrue($rm->isAbstract());
  }

  /**
   * `getState` must be declared abstract.
   */
  public function testGetStateIsAbstract(): void
  {
    $rm = new ReflectionMethod(BaseStorage::class, 'getState');

    self::assertTrue($rm->isAbstract());
  }

  /**
   * `setData` must be declared abstract.
   */
  public function testSetDataIsAbstract(): void
  {
    $rm = new ReflectionMethod(BaseStorage::class, 'setData');

    self::assertTrue($rm->isAbstract());
  }

  /**
   * `getData` must be declared abstract.
   */
  public function testGetDataIsAbstract(): void
  {
    $rm = new ReflectionMethod(BaseStorage::class, 'getData');

    self::assertTrue($rm->isAbstract());
  }

  /**
   * `close` must be declared abstract.
   */
  public function testCloseIsAbstract(): void
  {
    $rm = new ReflectionMethod(BaseStorage::class, 'close');

    self::assertTrue($rm->isAbstract());
  }

  /**
   * `getValue` must NOT be abstract (it is a concrete default implementation).
   */
  public function testGetValueIsNotAbstract(): void
  {
    $rm = new ReflectionMethod(BaseStorage::class, 'getValue');

    self::assertFalse($rm->isAbstract());
  }

  /**
   * `updateData` must NOT be abstract (it is a concrete default implementation).
   */
  public function testUpdateDataIsNotAbstract(): void
  {
    $rm = new ReflectionMethod(BaseStorage::class, 'updateData');

    self::assertFalse($rm->isAbstract());
  }

  // ------------------------------------------------------------------ //
  // getValue — concrete default implementation
  // ------------------------------------------------------------------ //

  /**
   * `getValue` returns the stored value when the key is present in the data map.
   */
  public function testGetValueReturnsPresentValue(): void
  {
    $this->storage->setData($this->key, ['foo' => 'bar', 'count' => 7]);

    self::assertSame('bar', $this->storage->getValue($this->key, 'foo'));
    self::assertSame(7, $this->storage->getValue($this->key, 'count'));
  }

  /**
   * `getValue` returns `null` (the default) when the key is absent and no
   * explicit default is provided.
   */
  public function testGetValueReturnsNullDefaultWhenKeyAbsentAndNoDefaultGiven(): void
  {
    $this->storage->setData($this->key, ['other' => 'value']);

    self::assertNull($this->storage->getValue($this->key, 'missing'));
  }

  /**
   * `getValue` returns the supplied `$default` when the dict key is absent.
   */
  public function testGetValueReturnsSuppliedDefaultWhenKeyAbsent(): void
  {
    $this->storage->setData($this->key, []);

    self::assertSame(42, $this->storage->getValue($this->key, 'nonexistent', 42));
    self::assertSame('fallback', $this->storage->getValue($this->key, 'nonexistent', 'fallback'));
  }

  /**
   * `getValue` returns `$default` when no data has been stored at all (empty map).
   */
  public function testGetValueOnEmptyStorageReturnsDefault(): void
  {
    // No setData call — getData returns [].
    self::assertNull($this->storage->getValue($this->key, 'anything'));
    self::assertSame('sentinel', $this->storage->getValue($this->key, 'anything', 'sentinel'));
  }

  /**
   * `getValue` correctly returns a stored `null` rather than `$default`.
   *
   * Uses `array_key_exists` to distinguish a present-but-null value from a
   * genuinely absent key, mirroring upstream Python's `data.get(key, default)`
   * which also distinguishes the two cases.
   */
  public function testGetValueReturnsStoredNullNotDefault(): void
  {
    $this->storage->setData($this->key, ['nullkey' => null]);

    self::assertNull($this->storage->getValue($this->key, 'nullkey', 'default_val'));
  }

  // ------------------------------------------------------------------ //
  // updateData — concrete default implementation
  // ------------------------------------------------------------------ //

  /**
   * `updateData` merges the patch into an existing record, preserving keys not
   * present in the patch.
   */
  public function testUpdateDataMergesPartialDataPreservingExistingKeys(): void
  {
    $this->storage->setData($this->key, ['a' => 1, 'b' => 2]);

    $result = $this->storage->updateData($this->key, ['b' => 20, 'c' => 3]);

    self::assertSame(['a' => 1, 'b' => 20, 'c' => 3], $result);
    self::assertSame(['a' => 1, 'b' => 20, 'c' => 3], $this->storage->getData($this->key));
  }

  /**
   * `updateData` on an empty (never-set) record creates the record from the patch.
   */
  public function testUpdateDataOnEmptyStorageCreatesRecord(): void
  {
    $result = $this->storage->updateData($this->key, ['x' => 'hello']);

    self::assertSame(['x' => 'hello'], $result);
    self::assertSame(['x' => 'hello'], $this->storage->getData($this->key));
  }

  /**
   * `updateData` returns a COPY: mutating the returned array does NOT mutate storage.
   *
   * Mirrors the `current_data.copy()` guarantee in upstream `base.py:178-181`.
   */
  public function testUpdateDataReturnsCopyNotReference(): void
  {
    $this->storage->setData($this->key, ['key' => 'original']);

    $returned = $this->storage->updateData($this->key, ['extra' => 'value']);

    // Mutate the returned array.
    $returned['injected'] = 'bleed';

    // Storage must remain unchanged.
    self::assertSame(['key' => 'original', 'extra' => 'value'], $this->storage->getData($this->key));
    self::assertArrayNotHasKey('injected', $this->storage->getData($this->key));
  }

  /**
   * `updateData` with an empty patch is a no-op on existing data and still
   * returns a copy of the current record.
   */
  public function testUpdateDataWithEmptyPatchReturnsCopyOfCurrentData(): void
  {
    $this->storage->setData($this->key, ['stable' => true]);

    $result = $this->storage->updateData($this->key, []);

    self::assertSame(['stable' => true], $result);
  }

  /**
   * `updateData` patch keys overwrite existing keys (last-write-wins via `array_merge`).
   */
  public function testUpdateDataPatchOverwritesExistingKeys(): void
  {
    $this->storage->setData($this->key, ['score' => 10]);

    $result = $this->storage->updateData($this->key, ['score' => 99]);

    self::assertSame(99, $result['score']);
    self::assertSame(99, $this->storage->getData($this->key)['score']);
  }

  /**
   * Full `test_update_data` contract from `test_storages.py`:
   * - initial data is empty
   * - first update with `{'foo': 'bar'}` returns `{'foo': 'bar'}`
   * - second update with `{}` returns `{'foo': 'bar'}` (unchanged)
   * - third update with `{'baz': 'spam'}` returns merged dict
   * - fourth update with `{'baz': 'test'}` overwrites baz
   *
   * Mirrors `TestStorages::test_update_data`.
   */
  public function testUpdateDataFullSequence(): void
  {
    // Empty to start.
    self::assertSame([], $this->storage->getData($this->key));

    // First update — creates record.
    $result = $this->storage->updateData($this->key, ['foo' => 'bar']);
    self::assertSame(['foo' => 'bar'], $result);

    // Empty patch — no change.
    $result = $this->storage->updateData($this->key, []);
    self::assertSame(['foo' => 'bar'], $result);
    self::assertSame(['foo' => 'bar'], $this->storage->getData($this->key));

    // Add baz.
    $result = $this->storage->updateData($this->key, ['baz' => 'spam']);
    self::assertSame(['foo' => 'bar', 'baz' => 'spam'], $result);
    self::assertSame(['foo' => 'bar', 'baz' => 'spam'], $this->storage->getData($this->key));

    // Overwrite baz.
    $result = $this->storage->updateData($this->key, ['baz' => 'test']);
    self::assertSame(['foo' => 'bar', 'baz' => 'test'], $result);
    self::assertSame(['foo' => 'bar', 'baz' => 'test'], $this->storage->getData($this->key));
  }

  // ------------------------------------------------------------------ //
  // Abstract delegation (sanity-checks the in-memory fixture itself)
  // ------------------------------------------------------------------ //

  /**
   * `setState` / `getState` round-trip via the concrete fixture.
   */
  public function testSetStateGetStateRoundTrip(): void
  {
    $this->storage->setState($this->key, 'MyState:idle');

    self::assertSame('MyState:idle', $this->storage->getState($this->key));
  }

  /**
   * `setState` with `null` clears the stored state.
   */
  public function testSetStateNullClearsState(): void
  {
    $this->storage->setState($this->key, 'active');
    $this->storage->setState($this->key, null);

    self::assertNull($this->storage->getState($this->key));
  }

  /**
   * `setData` / `getData` round-trip via the concrete fixture.
   */
  public function testSetDataGetDataRoundTrip(): void
  {
    $data = ['name' => 'Alice', 'step' => 3];
    $this->storage->setData($this->key, $data);

    self::assertSame($data, $this->storage->getData($this->key));
  }
}

// ------------------------------------------------------------------ //
// In-memory fixture (private to this test file)
// ------------------------------------------------------------------ //

/**
 * Minimal in-memory `BaseStorage` concrete subclass for testing.
 *
 * Stores state and data keyed by a synthetic string
 * `"<botId>:<chatId>:<userId>:<destiny>"` derived from the `StorageKey`.
 *
 * Not production-ready; covers the abstract surface only.
 */
final class InMemoryStorage extends BaseStorage
{
  /** @var array<string, null|string> */
  private array $states = [];

  /** @var array<string, array<string, mixed>> */
  private array $data = [];

  private function keyStr(StorageKey $key): string
  {
    return "{$key->botId}:{$key->chatId}:{$key->userId}:{$key->destiny}";
  }

  public function setState(StorageKey $key, object|string|null $state = null): void
  {
    if ($state === null) {
      $this->states[$this->keyStr($key)] = null;

      return;
    }

    $this->states[$this->keyStr($key)] = is_object($state)
      ? $state::class
      : $state;
  }

  public function getState(StorageKey $key): ?string
  {
    return $this->states[$this->keyStr($key)] ?? null;
  }

  public function setData(StorageKey $key, array $data): void
  {
    $this->data[$this->keyStr($key)] = $data;
  }

  /**
   * @return array<string, mixed>
   */
  public function getData(StorageKey $key): array
  {
    return $this->data[$this->keyStr($key)] ?? [];
  }

  public function close(): void
  {
    $this->states = [];
    $this->data = [];
  }
}
