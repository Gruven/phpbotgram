<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Storage;

use Amp\Sync\Lock as AmpLock;
use Closure;
use InvalidArgumentException;

/**
 * A thin wrapper around an optional `Amp\Sync\Lock` that implements the
 * acquire/release pattern for FSM event isolation.
 *
 * Ported from the async context-manager pattern of
 * `aiogram.fsm.storage.base.BaseEventIsolation.lock`
 * (`aiogram/fsm/storage/base.py:200-208`).
 *
 * The `$inner` is nullable to support `DisabledEventIsolation`, which needs a
 * no-op lock. An optional `$releaseFn` closure allows Redis-backed (or other
 * distributed) implementations to supply a custom release action without
 * subclassing. A `$released` guard prevents double-release: calling
 * `release()` more than once is silently ignored.
 */
final class Lock
{
  private bool $released = false;

  /**
   * Construct a `Lock` in one of two exclusive modes:
   *
   * - **Amp mode**: supply `$inner` (an `Amp\Sync\Lock`) and leave
   *   `$releaseFn` null. `release()` calls `$inner->release()`.
   * - **Custom-fn mode**: supply `$releaseFn` and leave `$inner` null.
   *   `release()` invokes the closure. Used by `RedisEventIsolation` where
   *   the release must execute a Lua check-and-delete atomically.
   * - **Disabled mode**: both null is valid for `DisabledEventIsolation`
   *   (a no-op lock).
   *
   * Providing **both** `$inner` and `$releaseFn` is a programming error —
   * it is ambiguous which release protocol to follow and would silently
   * leak the `$inner` Amp lock unreleased. The constructor rejects this
   * to fail fast.
   *
   * @param null|Closure(): void $releaseFn Optional custom release callback.
   *
   * @throws InvalidArgumentException When both `$inner` and `$releaseFn` are provided.
   */
  public function __construct(
    private readonly ?AmpLock $inner,
    private readonly ?Closure $releaseFn = null,
  ) {
    if ($inner !== null && $releaseFn !== null) {
      throw new InvalidArgumentException(
        'Lock accepts either an Amp\Sync\Lock or a releaseFn, not both.',
      );
    }
  }

  /**
   * Releases the underlying lock. Idempotent — subsequent calls are no-ops.
   *
   * When a custom `$releaseFn` was provided at construction, it is invoked and
   * `$inner` is left alone (the closure handles the full release protocol).
   * When only `$inner` is present, the standard `Amp\Sync\Lock::release()` is
   * called.
   */
  public function release(): void
  {
    if ($this->released) {
      return;
    }

    $this->released = true;

    if ($this->releaseFn !== null) {
      ($this->releaseFn)();

      return;
    }

    $this->inner?->release();
  }
}
