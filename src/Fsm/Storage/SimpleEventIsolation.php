<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Storage;

use Amp\Sync\LocalKeyedMutex;

/**
 * A mutex-backed event isolation strategy that serialises concurrent updates
 * sharing the same `StorageKey`.
 *
 * Ported from `aiogram.fsm.storage.memory.SimpleEventIsolation`
 * (`aiogram/fsm/storage/memory.py:85-97`). The upstream implementation holds a
 * `defaultdict` of `asyncio.Lock` instances keyed by `StorageKey`. The PHP port
 * uses `Amp\Sync\LocalKeyedMutex`, which already manages per-key lock instances
 * internally, so no `_locks` dict is needed here.
 *
 * Key derivation: the storage key string is built by joining all `StorageKey`
 * fields with a colon separator. This mirrors the full-fidelity key used by
 * `DefaultKeyBuilder(withBotId=true, withBusinessConnectionId=true, withDestiny=true)`.
 *
 * `close()` behaviour: upstream clears its `defaultdict` to release all in-memory
 * lock objects. `LocalKeyedMutex` does not expose a `clear()` method; resetting the
 * instance to a fresh `LocalKeyedMutex` achieves the same effect — any pending
 * acquires on the old mutex will still complete, and future acquires use the new
 * (empty) instance.
 */
final class SimpleEventIsolation extends BaseEventIsolation
{
  private LocalKeyedMutex $mutex;

  public function __construct()
  {
    $this->mutex = new LocalKeyedMutex();
  }

  /**
   * Acquires a per-key mutex lock for `$key`.
   *
   * Blocks (suspends the current fiber) until the lock is available.
   * The returned `Lock` wraps the underlying `Amp\Sync\Lock`; call
   * `release()` in a `finally` block to free the mutex.
   */
  public function lock(StorageKey $key): Lock
  {
    $ampLock = $this->mutex->acquire($this->buildKey($key));

    return new Lock($ampLock);
  }

  /**
   * Resets the internal mutex to a fresh instance, releasing all tracked
   * per-key lock objects.
   *
   * Mirrors upstream's `SimpleEventIsolation.close()` which clears the
   * internal `_locks` defaultdict (`memory.py:97`).
   */
  public function close(): void
  {
    $this->mutex = new LocalKeyedMutex();
  }

  /**
   * Derives a stable string key from all `StorageKey` fields.
   *
   * Format: `<botId>:<chatId>:<threadId|"">:<userId>:<businessConnectionId|"">:<destiny>`
   */
  private function buildKey(StorageKey $key): string
  {
    return implode(':', [
      $key->botId,
      $key->chatId,
      $key->threadId ?? '',
      $key->userId,
      $key->businessConnectionId ?? '',
      $key->destiny,
    ]);
  }
}
