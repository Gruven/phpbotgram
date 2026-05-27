<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\ChatAction;

use Gruven\PhpBotGram\Enums\ChatAction;
use Gruven\PhpBotGram\Methods\SendChatAction;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Tests\Support\RunAsyncTrait;
use Gruven\PhpBotGram\Utils\ChatAction\ChatActionHandle;
use Gruven\PhpBotGram\Utils\ChatAction\ChatActionSender;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Amp\delay;

/**
 * Unit tests for {@see ChatActionSender} and {@see ChatActionHandle}.
 *
 * Uses a tiny interval (0.01 s) and a body that delays briefly so the
 * background loop has time to fire without needing real 5-second windows.
 * All tests run inside {@see RunAsyncTrait::runAsync()} to provide the
 * Revolt event-loop fibre context that Amp\async and Amp\delay require.
 *
 * Port of upstream `tests/test_utils/test_chat_action.py`.
 *
 * Upstream skips
 * --------------
 * - `test_wait`: calls `sender._wait(1)` directly; PHP has no public `_wait`
 *   method — API divergence (a).
 * - `test_contextmanager`: uses `async with sender` (Python async context
 *   manager) and `sender._close_event.is_set()`; PHP uses
 *   `scope()` / `start()` / `stop()` — API divergence (a).
 * - `test_worker`: patches `Bot.send_chat_action` via `unittest.mock.AsyncMock`
 *   at the class level — test infrastructure divergence (c); equivalent
 *   behavior is covered by `testScopeCallsSendChatActionAtLeastOnce`.
 *
 * @internal
 */
final class ChatActionSenderTest extends TestCase
{
  use RunAsyncTrait;

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  private static function makeBot(): MockedBot
  {
    $bot = new MockedBot();

    // Pre-load plenty of canned `true` responses for sendChatAction calls.
    for ($i = 0; $i < 20; $i++) {
      $bot->addResultFor(SendChatAction::class, ok: true, result: true);
    }

    return $bot;
  }

  // ---------------------------------------------------------------------------
  // scope() — basic lifecycle
  // ---------------------------------------------------------------------------

  public function testScopeCallsSendChatActionAtLeastOnce(): void
  {
    // While a short body is running (0.05 s) with interval=0.01 s, the loop
    // should fire at least once.
    $this->runAsync(static function (): void {
      $bot = self::makeBot();

      ChatActionSender::typing(bot: $bot, chatId: 123, interval: 0.01)->scope(
        static function (): void {
          delay(0.05);
        },
      );

      $session = $bot->getMockedSession();
      // At least one sendChatAction request must have been recorded.
      self::assertGreaterThanOrEqual(1, count($session->requestTimeouts));
    });
  }

  public function testScopeStopsCallingAfterBodyReturns(): void
  {
    // After scope() returns, no further requests should be dispatched.
    // We record the count just after scope() and verify no more come in.
    $this->runAsync(static function (): void {
      $bot = self::makeBot();
      $sender = ChatActionSender::typing(bot: $bot, chatId: 123, interval: 0.01);

      $sender->scope(static fn(): null => delay(0.03));

      // Snapshot the request count immediately after scope exits.
      $countAfterScope = count($bot->getMockedSession()->requestTimeouts);

      // Small wait to verify the loop truly stopped.
      delay(0.05);

      $countAfterWait = count($bot->getMockedSession()->requestTimeouts);
      self::assertSame($countAfterScope, $countAfterWait);
    });
  }

  public function testScopeReturnsBodyReturnValue(): void
  {
    // scope() must propagate the return value of the body closure.
    $this->runAsync(static function (): void {
      $bot = self::makeBot();
      $sender = ChatActionSender::typing(bot: $bot, chatId: 42, interval: 0.01);

      $result = $sender->scope(static fn(): int => 99);

      self::assertSame(99, $result);
    });
  }

  // ---------------------------------------------------------------------------
  // Manual start() / stop() lifecycle
  // ---------------------------------------------------------------------------

  public function testStartAndStopManualLifecycle(): void
  {
    // start() returns a handle; stop() terminates the loop.
    $this->runAsync(static function (): void {
      $bot = self::makeBot();
      $sender = ChatActionSender::typing(bot: $bot, chatId: 5, interval: 0.01);

      $handle = $sender->start();
      self::assertInstanceOf(ChatActionHandle::class, $handle);

      // Let it run briefly.
      delay(0.05);

      $handle->stop();

      $countAfterStop = count($bot->getMockedSession()->requestTimeouts);

      // No further calls after stop.
      delay(0.05);

      self::assertSame($countAfterStop, count($bot->getMockedSession()->requestTimeouts));
      self::assertGreaterThanOrEqual(1, $countAfterStop);
    });
  }

  public function testStopIsIdempotent(): void
  {
    // Calling stop() twice must not throw or lock up.
    $this->runAsync(static function (): void {
      $bot = self::makeBot();
      $sender = ChatActionSender::typing(bot: $bot, chatId: 7, interval: 0.01);

      $handle = $sender->start();
      delay(0.02);
      $handle->stop();
      $handle->stop(); // second call must be a no-op

      // If we reach this line without exception, idempotency holds.
      // Assert at least one action was fired during the 0.02s window.
      self::assertGreaterThanOrEqual(1, count($bot->getMockedSession()->requestTimeouts));
    });
  }

  // ---------------------------------------------------------------------------
  // initialSleep
  // ---------------------------------------------------------------------------

  public function testInitialSleepDelaysFirstAction(): void
  {
    // With initialSleep > body duration, no action should fire during the body.
    $this->runAsync(static function (): void {
      $bot = self::makeBot();
      // initialSleep of 1 s — far longer than the body (0.02 s).
      $sender = new ChatActionSender(
        bot: $bot,
        chatId: 1,
        initialSleep: 1.0,
        interval: 0.01,
      );

      $sender->scope(static fn(): null => delay(0.02));

      self::assertCount(0, $bot->getMockedSession()->requestTimeouts);
    });
  }

  // ---------------------------------------------------------------------------
  // Static factories — action string correctness
  // ---------------------------------------------------------------------------

  /** @return array<string, array{0: string, 1: string}> */
  public static function factoryProvider(): array
  {
    return [
      'typing' => ['typing', ChatAction::Typing->value],
      'uploadPhoto' => ['uploadPhoto', ChatAction::UploadPhoto->value],
      'recordVideo' => ['recordVideo', ChatAction::RecordVideo->value],
      'uploadVideo' => ['uploadVideo', ChatAction::UploadVideo->value],
      'recordVoice' => ['recordVoice', ChatAction::RecordVoice->value],
      'uploadVoice' => ['uploadVoice', ChatAction::UploadVoice->value],
      'uploadDocument' => ['uploadDocument', ChatAction::UploadDocument->value],
      'findLocation' => ['findLocation', ChatAction::FindLocation->value],
      'recordVideoNote' => ['recordVideoNote', ChatAction::RecordVideoNote->value],
      'uploadVideoNote' => ['uploadVideoNote', ChatAction::UploadVideoNote->value],
      'chooseSticker' => ['chooseSticker', ChatAction::ChooseSticker->value],
    ];
  }

  #[DataProvider('factoryProvider')]
  public function testStaticFactorySetsCorrectAction(string $factory, string $expectedAction): void
  {
    // Each factory must produce a sender with the matching action string.
    // We drive it for one tick and inspect the recorded request.
    $this->runAsync(static function () use ($factory, $expectedAction): void {
      $bot = self::makeBot();

      /** @var ChatActionSender $sender */
      $sender = ChatActionSender::{$factory}(bot: $bot, chatId: 1, interval: 0.01);

      $sender->scope(static fn(): null => delay(0.03));

      $session = $bot->getMockedSession();

      self::assertGreaterThanOrEqual(1, count($session->requestTimeouts));

      // Inspect the first recorded request.
      $method = $session->getRequest();
      self::assertInstanceOf(SendChatAction::class, $method);
      self::assertSame($expectedAction, $method->action);
    });
  }

  // ---------------------------------------------------------------------------
  // messageThreadId propagation
  // ---------------------------------------------------------------------------

  public function testMessageThreadIdIsForwardedToSendChatAction(): void
  {
    $this->runAsync(static function (): void {
      $bot = self::makeBot();
      $sender = new ChatActionSender(
        bot: $bot,
        chatId: 99,
        messageThreadId: 7,
        action: 'typing',
        interval: 0.01,
      );

      $sender->scope(static fn(): null => delay(0.03));

      $method = $bot->getMockedSession()->getRequest();
      self::assertInstanceOf(SendChatAction::class, $method);
      self::assertSame(7, $method->messageThreadId);
    });
  }

  // ---------------------------------------------------------------------------
  // raceDelay cancellation — no orphaned timers
  // ---------------------------------------------------------------------------

  public function testStopReclaimsDelayPromptly(): void
  {
    // With interval=5s, if stop() did NOT cancel the orphaned delay the test
    // would stall for ~5 s. The DeferredCancellation fix means awaitFirst
    // returns quickly when close wins, so the whole test completes in <0.1 s.
    $this->runAsync(static function (): void {
      $bot = self::makeBot();
      $sender = ChatActionSender::typing(bot: $bot, chatId: 1, interval: 5.0);

      $start = microtime(true);
      $handle = $sender->start();

      // Give the first action a chance to fire (immediate on initialSleep=0).
      delay(0.02);

      $handle->stop();

      $elapsed = microtime(true) - $start;

      // Total elapsed should be well under 1 s — proves the 5 s delay timer
      // was cancelled, not left to expire.
      self::assertLessThan(1.0, $elapsed, 'stop() stalled — orphaned 5s delay timer not cancelled');
      self::assertGreaterThanOrEqual(1, count($bot->getMockedSession()->requestTimeouts));
    });
  }

  // ---------------------------------------------------------------------------
  // stop() concurrent with running loop
  // ---------------------------------------------------------------------------

  public function testConcurrentStopTerminatesLoop(): void
  {
    // start() in one async fiber, stop() from the caller fiber after a small
    // delay — verifies the close signal unblocks the delay inside the loop.
    $this->runAsync(static function (): void {
      $bot = self::makeBot();
      $sender = ChatActionSender::typing(bot: $bot, chatId: 3, interval: 5.0);

      // Long interval so without stop() it would wait 5 s.
      $handle = $sender->start();

      // Give the first action a chance to fire (initial sleep 0, immediate).
      delay(0.02);

      $handle->stop();

      // stop() must return promptly (not hang for 5 s).
      // Assert at least one action was fired in the 0.02 s window.
      self::assertGreaterThanOrEqual(1, count($bot->getMockedSession()->requestTimeouts));
    });
  }
}
