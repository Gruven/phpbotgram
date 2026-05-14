<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\ChatAction;

use function Amp\async;

use Amp\DeferredCancellation;
use Amp\DeferredFuture;

use function Amp\delay;

use Amp\Future;

use function Amp\Future\awaitFirst;
use function Amp\now;

use Closure;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Enums\ChatAction;
use Throwable;

/**
 * Periodically emits a `sendChatAction` call to Telegram while a long
 * operation is running, giving the user visual feedback that the bot is
 * active.
 *
 * Mirrors upstream `aiogram.utils.chat_action.ChatActionSender`.
 *
 * Usage (explicit):
 *
 * ```php
 * $handle = $sender->start();
 * try {
 *     // long operation
 * } finally {
 *     $handle->stop();
 * }
 * ```
 *
 * Usage (scoped helper):
 *
 * ```php
 * $result = $sender->scope(function () {
 *     return doLongWork();
 * });
 * ```
 *
 * Or via static factories:
 *
 * ```php
 * ChatActionSender::typing(bot: $bot, chatId: 123)->scope(fn () => longOp());
 * ```
 */
final class ChatActionSender
{
  public function __construct(
    private readonly Bot $bot,
    private readonly int|string $chatId,
    private readonly ?int $messageThreadId = null,
    private readonly string $action = 'typing',
    private readonly float $interval = 5.0,
    private readonly float $initialSleep = 0.0,
  ) {}

  /**
   * Start the background chat-action loop.
   *
   * Returns a {@see ChatActionHandle} whose {@see ChatActionHandle::stop()}
   * method must be called to stop the loop. Prefer {@see scope()} over
   * manual start/stop.
   */
  public function start(): ChatActionHandle
  {
    /** @var DeferredFuture<null> $closeSignal */
    $closeSignal = new DeferredFuture();

    $action = $this->action;
    $bot = $this->bot;
    $chatId = $this->chatId;
    $messageThreadId = $this->messageThreadId;
    $interval = $this->interval;
    $initialSleep = $this->initialSleep;

    /** @var Future<null> $task */
    $task = async(static function () use (
      $bot,
      $chatId,
      $messageThreadId,
      $action,
      $interval,
      $initialSleep,
      $closeSignal,
    ): null {
      $closeFuture = $closeSignal->getFuture();

      // Initial sleep with early-exit support.
      if ($initialSleep > 0.0) {
        self::raceDelay($initialSleep, $closeFuture);
      }

      while (!$closeFuture->isComplete()) {
        $start = now();

        try {
          $bot->sendChatAction(
            chatId: $chatId,
            action: $action,
            messageThreadId: $messageThreadId,
          );
        } catch (Throwable) {
          // Swallow errors silently — a failed sendChatAction must not
          // kill the background loop (mirrors upstream behaviour where the
          // coroutine catches asyncio.CancelledError only).
        }

        $elapsed = now() - $start;
        $remaining = $interval - $elapsed;

        if ($remaining > 0.0) {
          self::raceDelay($remaining, $closeFuture);
        }
      }

      return null;
    });

    return new ChatActionHandle(task: $task, closeSignal: $closeSignal);
  }

  /**
   * Run `$body` while the chat-action loop is active. Stops the loop when
   * `$body` returns or throws.
   *
   * @template R
   *
   * @param Closure(): R $body
   *
   * @return R
   */
  public function scope(Closure $body): mixed
  {
    $handle = $this->start();

    try {
      return $body();
    } finally {
      $handle->stop();
    }
  }

  // ---------------------------------------------------------------------------
  // Static factories — one per ChatAction enum case.
  // ---------------------------------------------------------------------------

  public static function typing(
    Bot $bot,
    int|string $chatId,
    ?int $messageThreadId = null,
    float $interval = 5.0,
    float $initialSleep = 0.0,
  ): self {
    return new self(
      bot: $bot,
      chatId: $chatId,
      messageThreadId: $messageThreadId,
      action: ChatAction::Typing->value,
      interval: $interval,
      initialSleep: $initialSleep,
    );
  }

  public static function uploadPhoto(
    Bot $bot,
    int|string $chatId,
    ?int $messageThreadId = null,
    float $interval = 5.0,
    float $initialSleep = 0.0,
  ): self {
    return new self(
      bot: $bot,
      chatId: $chatId,
      messageThreadId: $messageThreadId,
      action: ChatAction::UploadPhoto->value,
      interval: $interval,
      initialSleep: $initialSleep,
    );
  }

  public static function recordVideo(
    Bot $bot,
    int|string $chatId,
    ?int $messageThreadId = null,
    float $interval = 5.0,
    float $initialSleep = 0.0,
  ): self {
    return new self(
      bot: $bot,
      chatId: $chatId,
      messageThreadId: $messageThreadId,
      action: ChatAction::RecordVideo->value,
      interval: $interval,
      initialSleep: $initialSleep,
    );
  }

  public static function uploadVideo(
    Bot $bot,
    int|string $chatId,
    ?int $messageThreadId = null,
    float $interval = 5.0,
    float $initialSleep = 0.0,
  ): self {
    return new self(
      bot: $bot,
      chatId: $chatId,
      messageThreadId: $messageThreadId,
      action: ChatAction::UploadVideo->value,
      interval: $interval,
      initialSleep: $initialSleep,
    );
  }

  public static function recordVoice(
    Bot $bot,
    int|string $chatId,
    ?int $messageThreadId = null,
    float $interval = 5.0,
    float $initialSleep = 0.0,
  ): self {
    return new self(
      bot: $bot,
      chatId: $chatId,
      messageThreadId: $messageThreadId,
      action: ChatAction::RecordVoice->value,
      interval: $interval,
      initialSleep: $initialSleep,
    );
  }

  public static function uploadVoice(
    Bot $bot,
    int|string $chatId,
    ?int $messageThreadId = null,
    float $interval = 5.0,
    float $initialSleep = 0.0,
  ): self {
    return new self(
      bot: $bot,
      chatId: $chatId,
      messageThreadId: $messageThreadId,
      action: ChatAction::UploadVoice->value,
      interval: $interval,
      initialSleep: $initialSleep,
    );
  }

  public static function uploadDocument(
    Bot $bot,
    int|string $chatId,
    ?int $messageThreadId = null,
    float $interval = 5.0,
    float $initialSleep = 0.0,
  ): self {
    return new self(
      bot: $bot,
      chatId: $chatId,
      messageThreadId: $messageThreadId,
      action: ChatAction::UploadDocument->value,
      interval: $interval,
      initialSleep: $initialSleep,
    );
  }

  public static function findLocation(
    Bot $bot,
    int|string $chatId,
    ?int $messageThreadId = null,
    float $interval = 5.0,
    float $initialSleep = 0.0,
  ): self {
    return new self(
      bot: $bot,
      chatId: $chatId,
      messageThreadId: $messageThreadId,
      action: ChatAction::FindLocation->value,
      interval: $interval,
      initialSleep: $initialSleep,
    );
  }

  public static function recordVideoNote(
    Bot $bot,
    int|string $chatId,
    ?int $messageThreadId = null,
    float $interval = 5.0,
    float $initialSleep = 0.0,
  ): self {
    return new self(
      bot: $bot,
      chatId: $chatId,
      messageThreadId: $messageThreadId,
      action: ChatAction::RecordVideoNote->value,
      interval: $interval,
      initialSleep: $initialSleep,
    );
  }

  public static function uploadVideoNote(
    Bot $bot,
    int|string $chatId,
    ?int $messageThreadId = null,
    float $interval = 5.0,
    float $initialSleep = 0.0,
  ): self {
    return new self(
      bot: $bot,
      chatId: $chatId,
      messageThreadId: $messageThreadId,
      action: ChatAction::UploadVideoNote->value,
      interval: $interval,
      initialSleep: $initialSleep,
    );
  }

  public static function chooseSticker(
    Bot $bot,
    int|string $chatId,
    ?int $messageThreadId = null,
    float $interval = 5.0,
    float $initialSleep = 0.0,
  ): self {
    return new self(
      bot: $bot,
      chatId: $chatId,
      messageThreadId: $messageThreadId,
      action: ChatAction::ChooseSticker->value,
      interval: $interval,
      initialSleep: $initialSleep,
    );
  }

  // ---------------------------------------------------------------------------
  // Private helpers
  // ---------------------------------------------------------------------------

  /**
   * Race a delay against the close-future. Returns when either the delay
   * elapses or the close signal fires — whichever comes first.
   *
   * Uses {@see DeferredCancellation} to cancel the underlying event-loop
   * timer when the close-future wins, preventing orphaned timers from
   * accumulating when many senders are stopped early.
   *
   * @param Future<null> $closeFuture
   */
  private static function raceDelay(float $seconds, Future $closeFuture): void
  {
    if ($closeFuture->isComplete() || $seconds <= 0.0) {
      return;
    }

    $cancellation = new DeferredCancellation();

    /** @var Future<null> $delayTask */
    $delayTask = async(static fn(): null => delay($seconds, true, $cancellation->getCancellation()));

    /** @var list<Future<null>> $futures */
    $futures = [$closeFuture, $delayTask];

    try {
      awaitFirst($futures);
    } finally {
      // Reclaim the event-loop timer if the close-future won the race.
      $cancellation->cancel();
      // Silence the unhandled-future warning that fires when the delay was
      // cancelled but the future was never awaited (amphp stdlib contract).
      $delayTask->ignore();
    }
  }
}
