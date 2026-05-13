<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Storage;

/**
 * Abstract base for FSM event-isolation strategies.
 *
 * Event isolation controls whether concurrent updates for the same
 * `StorageKey` are serialised (one-at-a-time) or allowed to race.
 *
 * Ported from `aiogram.fsm.storage.base.BaseEventIsolation`
 * (`aiogram/fsm/storage/base.py:200-208`).
 *
 * The upstream Python implementation uses `@asynccontextmanager`, which
 * yields `None` and relies on a `try/finally` inside the context-manager
 * body to release the underlying primitive. PHP has no context-manager
 * syntax, so we expose the equivalent acquire/release pair via a `Lock`
 * value-object (Option A from the spec). Caller pattern:
 *
 * ```php
 * $lock = $isolation->lock($key);
 * try {
 *     // critical section
 * } finally {
 *     $lock->release();
 * }
 * ```
 */
abstract class BaseEventIsolation
{
  /**
   * Acquire an isolation lock for `$key`.
   *
   * Returns a `Lock` whose `release()` must be called (preferably from a
   * `finally` block) once the critical section is done.
   *
   * @param StorageKey $key Storage address to lock.
   */
  abstract public function lock(StorageKey $key): Lock;

  /**
   * Release all resources held by this isolation instance.
   *
   * Mirrors `BaseEventIsolation.close` (upstream: abstract async def close).
   */
  abstract public function close(): void;
}
