<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Storage;

use Amp\Sync\Lock as AmpLock;

/**
 * A thin wrapper around an optional `Amp\Sync\Lock` that implements the
 * acquire/release pattern for FSM event isolation.
 *
 * Ported from the async context-manager pattern of
 * `aiogram.fsm.storage.base.BaseEventIsolation.lock`
 * (`aiogram/fsm/storage/base.py:200-208`).
 *
 * The `$inner` is nullable to support `DisabledEventIsolation`, which needs a
 * no-op lock. A `$released` guard prevents double-release: calling `release()`
 * more than once is silently ignored.
 */
final class Lock
{
  private bool $released = false;

  public function __construct(
    private readonly ?AmpLock $inner,
  ) {}

  /**
   * Releases the underlying lock. Idempotent — subsequent calls are no-ops.
   */
  public function release(): void
  {
    if ($this->released) {
      return;
    }

    $this->released = true;
    $this->inner?->release();
  }
}
