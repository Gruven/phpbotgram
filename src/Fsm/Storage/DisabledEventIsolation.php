<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Storage;

/**
 * A no-op event isolation strategy — concurrent updates are never serialised.
 *
 * Ported from `aiogram.fsm.storage.memory.DisabledEventIsolation`
 * (`aiogram/fsm/storage/memory.py:72-82`). The upstream implementation yields
 * `None` immediately (no lock is acquired); the PHP port returns a `Lock(null)`
 * whose `release()` is also a no-op.
 *
 * Use this as the default isolation strategy when you do not need mutual
 * exclusion between concurrent updates sharing the same `StorageKey`.
 */
final class DisabledEventIsolation extends BaseEventIsolation
{
  /**
   * Returns a no-op lock. No mutex is acquired; `release()` is a no-op.
   */
  public function lock(StorageKey $key): Lock
  {
    return new Lock(null);
  }

  /**
   * No-op — there are no resources to release.
   */
  public function close(): void {}
}
