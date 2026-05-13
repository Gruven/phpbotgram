<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm\Storage;

use Gruven\PhpBotGram\Fsm\Storage\BaseStorage;
use Gruven\PhpBotGram\Fsm\Storage\MemoryStorage;
use Gruven\PhpBotGram\Fsm\Storage\StorageKey;
use PHPUnit\Framework\TestCase;

/**
 * Upstream `tests/test_fsm/storage/test_storages.py` `memory_storage`
 * parametrize rows deliberately not ported here:
 *
 * - `TestStorages::test_set_data` with `TypedDict` — API divergence: PHP has
 *   no `TypedDict` equivalent; `setData` accepts `array<string,mixed>`.
 * - `TestStorages::test_set_data` invalid-type `DataNotDictLikeError` case —
 *   PHP `MemoryStorage::setData` accepts `array` by type hint; the exception
 *   guard is a concern for typed storage layers, not the bare memory impl.
 *
 * All other upstream `memory_storage` cases are ported in this file.
 */
final class MemoryStorageTest extends TestCase
{
  private MemoryStorage $storage;
  private StorageKey $key;

  protected function setUp(): void
  {
    $this->storage = new MemoryStorage();
    $this->key = new StorageKey(botId: 1, chatId: 100, userId: 42);
  }

  // ------------------------------------------------------------------ //
  // Structural checks
  // ------------------------------------------------------------------ //

  /**
   * `MemoryStorage` must extend `BaseStorage`.
   */
  public function testMemoryStorageExtendsBaseStorage(): void
  {
    self::assertInstanceOf(BaseStorage::class, $this->storage);
  }

  // ------------------------------------------------------------------ //
  // setState / getState
  // ------------------------------------------------------------------ //

  /**
   * `setState` + `getState` round-trip with a plain string.
   */
  public function testSetStateGetStateRoundTripWithString(): void
  {
    $this->storage->setState($this->key, 'MyGroup:idle');

    self::assertSame('MyGroup:idle', $this->storage->getState($this->key));
  }

  /**
   * `setState(null)` clears the state.
   */
  public function testSetStateNullClearsState(): void
  {
    $this->storage->setState($this->key, 'active');
    $this->storage->setState($this->key, null);

    self::assertNull($this->storage->getState($this->key));
  }

  /**
   * `getState` returns `null` for a key that has never been written.
   */
  public function testGetStateReturnNullForUnknownKey(): void
  {
    $unknown = new StorageKey(botId: 99, chatId: 99, userId: 99);

    self::assertNull($this->storage->getState($unknown));
  }

  /**
   * `setState` with an object that has a public `state` property stores
   * the property value (future `State` contract, Task 5.5).
   *
   * @todo Task 5.5: Replace the anonymous object with `instanceof State` once
   *       `Gruven\PhpBotGram\Fsm\State` exists.
   */
  public function testSetStateWithStateObjectExtractsStateProperty(): void
  {
    $stateObj = new class {
      public string $state = 'MyGroup:step_one';
    };

    $this->storage->setState($this->key, $stateObj);

    self::assertSame('MyGroup:step_one', $this->storage->getState($this->key));
  }

  // ------------------------------------------------------------------ //
  // setData / getData
  // ------------------------------------------------------------------ //

  /**
   * `setData` + `getData` round-trip with a small dict.
   */
  public function testSetDataGetDataRoundTrip(): void
  {
    $data = ['name' => 'Alice', 'step' => 3];
    $this->storage->setData($this->key, $data);

    self::assertSame($data, $this->storage->getData($this->key));
  }

  /**
   * `getData` returns an empty array for a key that has never been written.
   */
  public function testGetDataReturnsEmptyArrayForUnknownKey(): void
  {
    $unknown = new StorageKey(botId: 99, chatId: 99, userId: 99);

    self::assertSame([], $this->storage->getData($unknown));
  }

  /**
   * `getData` returns a COPY: mutating the returned array does NOT mutate storage.
   *
   * PHP arrays are value-typed, so the copy semantic is automatic.
   * Mirrors upstream `return self.storage[key].data.copy()` (`memory.py:57`).
   */
  public function testGetDataReturnsCopyNotReference(): void
  {
    $this->storage->setData($this->key, ['x' => 1]);

    $copy = $this->storage->getData($this->key);
    $copy['injected'] = 'bleed';

    // Storage must be unchanged.
    self::assertSame(['x' => 1], $this->storage->getData($this->key));
    self::assertArrayNotHasKey('injected', $this->storage->getData($this->key));
  }

  /**
   * `setData` on an already-set key replaces (does NOT merge) the data.
   *
   * Mirrors upstream `self.storage[key].data = data.copy()` (`memory.py:54`).
   */
  public function testSetDataReplacesExistingData(): void
  {
    $this->storage->setData($this->key, ['a' => 1, 'b' => 2]);
    $this->storage->setData($this->key, ['c' => 3]);

    self::assertSame(['c' => 3], $this->storage->getData($this->key));
  }

  // ------------------------------------------------------------------ //
  // updateData (delegated to BaseStorage::updateData)
  // ------------------------------------------------------------------ //

  /**
   * `updateData` merges the patch into the existing record and returns the
   * merged result.
   */
  public function testUpdateDataMergesAndReturnsResult(): void
  {
    $this->storage->setData($this->key, ['a' => 1, 'b' => 2]);

    $result = $this->storage->updateData($this->key, ['b' => 20, 'c' => 3]);

    self::assertSame(['a' => 1, 'b' => 20, 'c' => 3], $result);
    self::assertSame(['a' => 1, 'b' => 20, 'c' => 3], $this->storage->getData($this->key));
  }

  // ------------------------------------------------------------------ //
  // getValue (inherited default from BaseStorage)
  // ------------------------------------------------------------------ //

  /**
   * `getValue` returns the stored value for a present key.
   */
  public function testGetValueReturnsPresentValue(): void
  {
    $this->storage->setData($this->key, ['score' => 42]);

    self::assertSame(42, $this->storage->getValue($this->key, 'score'));
  }

  /**
   * `getValue` returns `$default` when the dict key is absent.
   */
  public function testGetValueReturnsDefaultForAbsentKey(): void
  {
    $this->storage->setData($this->key, []);

    self::assertNull($this->storage->getValue($this->key, 'missing'));
    self::assertSame('fallback', $this->storage->getValue($this->key, 'missing', 'fallback'));
  }

  // ------------------------------------------------------------------ //
  // Key isolation
  // ------------------------------------------------------------------ //

  /**
   * Two distinct `StorageKey` instances do not interfere with each other.
   */
  public function testDistinctKeysAreIsolated(): void
  {
    $keyA = new StorageKey(botId: 1, chatId: 2, userId: 3);
    $keyB = new StorageKey(botId: 1, chatId: 2, userId: 4);

    $this->storage->setState($keyA, 'state_a');
    $this->storage->setState($keyB, 'state_b');
    $this->storage->setData($keyA, ['owner' => 'a']);
    $this->storage->setData($keyB, ['owner' => 'b']);

    self::assertSame('state_a', $this->storage->getState($keyA));
    self::assertSame('state_b', $this->storage->getState($keyB));
    self::assertSame(['owner' => 'a'], $this->storage->getData($keyA));
    self::assertSame(['owner' => 'b'], $this->storage->getData($keyB));
  }

  /**
   * Same bot/chat/user but different `destiny` tags produce isolated slots.
   */
  public function testDifferentDestinyTagsAreIsolated(): void
  {
    $keyDefault = new StorageKey(botId: 1, chatId: 100, userId: 42);
    $keyWizard = new StorageKey(botId: 1, chatId: 100, userId: 42, destiny: 'wizard');

    $this->storage->setState($keyDefault, 'default_state');
    $this->storage->setState($keyWizard, 'wizard_state');

    self::assertSame('default_state', $this->storage->getState($keyDefault));
    self::assertSame('wizard_state', $this->storage->getState($keyWizard));
  }

  // ------------------------------------------------------------------ //
  // close()
  // ------------------------------------------------------------------ //

  /**
   * `close()` is a no-op in the sense that it does not throw; after close,
   * all stored data is gone.
   */
  public function testCloseIsCallableAndClearsStorage(): void
  {
    $this->storage->setState($this->key, 'active');
    $this->storage->setData($this->key, ['k' => 'v']);

    // Must not throw.
    $this->storage->close();

    // Records are gone after close.
    self::assertNull($this->storage->getState($this->key));
    self::assertSame([], $this->storage->getData($this->key));
  }
}
