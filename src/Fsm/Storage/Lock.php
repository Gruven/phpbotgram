<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Storage;

use Amp\Sync\Lock as AmpLock;
use Closure;

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
   * @param null|Closure(): void $releaseFn Optional custom release callback.
   *                                        When provided, it is invoked instead of
   *                                        (or in addition to — see below) `$inner->release()`.
   *                                        If both `$inner` and `$releaseFn` are set,
   *                                        `$releaseFn` takes precedence and `$inner` is ignored.
   *                                        This supports Redis-backed locks where the release
   *                                        must atomically check-and-delete the token.
   */
  public function __construct(
    private readonly ?AmpLock $inner,
    private readonly ?Closure $releaseFn = null,
  ) {}

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
