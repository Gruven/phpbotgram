<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\ChatAction;

use Amp\DeferredFuture;
use Amp\Future;

/**
 * A handle returned by {@see ChatActionSender::start()}.
 *
 * Calling {@see stop()} signals the background chat-action loop to terminate
 * and blocks until the loop fiber has exited. Multiple calls to `stop()` are
 * safe — subsequent calls after the first are no-ops.
 *
 * Mirrors the `__aexit__` / `close()` path of upstream
 * `aiogram.utils.chat_action.ChatActionSender`.
 */
final class ChatActionHandle
{
  private bool $stopped = false;

  /**
   * @param Future<null> $task Background loop future (from Amp\async).
   * @param DeferredFuture<null> $closeSignal Resolved to stop the loop.
   */
  public function __construct(
    private readonly Future $task,
    private readonly DeferredFuture $closeSignal,
  ) {}

  /**
   * Stop the background chat-action loop and wait for it to exit.
   *
   * Idempotent — multiple calls are safe.
   */
  public function stop(): void
  {
    if ($this->stopped) {
      return;
    }

    $this->stopped = true;

    if (!$this->closeSignal->isComplete()) {
      $this->closeSignal->complete(null);
    }

    $this->task->await();
  }
}
