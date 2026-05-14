<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\ChatAction;

use function Amp\delay;

use Gruven\PhpBotGram\Dispatcher\Event\HandlerObject;
use Gruven\PhpBotGram\Dispatcher\Middlewares\BaseMiddleware;
use Gruven\PhpBotGram\Methods\SendChatAction;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Tests\Support\RunAsyncTrait;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Utils\ChatAction\ChatActionMiddleware;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see ChatActionMiddleware}.
 *
 * Verifies that:
 *
 * - A `Message` event processed by a handler with the `chat_action` flag
 *   triggers a {@see ChatActionSender} loop during handler execution.
 * - A handler WITHOUT the flag passes through unchanged (no sender started).
 * - Non-Message events are passed through regardless of the flag.
 * - The flag value controls the action string sent.
 *
 * Port of upstream `tests/test_utils/test_chat_action.py`
 * `TestChatActionMiddleware`.
 *
 * Upstream skips
 * --------------
 * - Upstream `test_call_default` patches `ChatActionSender._run` and
 *   `ChatActionSender._stop` via `AsyncMock` and uses
 *   `flags.chat_action(value)(handler)` to decorate handlers; PHP has no
 *   equivalent attribute-based flag decorator — test infrastructure divergence
 *   (c); equivalent behavior is covered by `testFlaggedHandlerTriggersActionDuringExecution`
 *   and related tests using `HandlerObject` with flags.
 */
final class ChatActionMiddlewareTest extends TestCase
{
  use RunAsyncTrait;

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  private static function makeMessage(int $chatId = 1, ?int $threadId = null, bool $isTopic = false): Message
  {
    return new Message(
      messageId: 1,
      date: new DateTime('2024-01-01'),
      chat: new Chat(id: $chatId, type: 'private'),
      messageThreadId: $threadId,
      isTopicMessage: $isTopic,
    );
  }

  private static function makeBot(int $responses = 20): MockedBot
  {
    $bot = new MockedBot();

    for ($i = 0; $i < $responses; $i++) {
      $bot->addResultFor(SendChatAction::class, ok: true, result: true);
    }

    return $bot;
  }

  /**
   * Build a real middleware with a tiny interval so tests run fast.
   */
  private static function middleware(float $interval = 0.01): ChatActionMiddleware
  {
    return new ChatActionMiddleware(interval: $interval);
  }

  // ---------------------------------------------------------------------------
  // Tests
  // ---------------------------------------------------------------------------

  public function testMiddlewareExtendsBaseMiddleware(): void
  {
    self::assertInstanceOf(BaseMiddleware::class, new ChatActionMiddleware());
  }

  public function testFlaggedHandlerTriggersActionDuringExecution(): void
  {
    // A Message event + handler with chat_action flag → at least one
    // sendChatAction call while the handler is running.
    $this->runAsync(static function (): void {
      $bot = self::makeBot();
      $middleware = self::middleware(interval: 0.01);
      $event = self::makeMessage(chatId: 42);

      $invoked = false;
      $handler = static function (object $e, array $d) use (&$invoked): string {
        // Simulate work; the background loop has time to fire.
        delay(0.05);
        $invoked = true;

        return 'done';
      };

      $handlerObject = new HandlerObject($handler, [], ['chat_action' => true]);
      $data = [
        'bot' => $bot,
        'handler' => $handlerObject,
      ];

      $result = $middleware($handler, $event, $data);

      self::assertTrue($invoked);
      self::assertSame('done', $result);
      self::assertGreaterThanOrEqual(1, count($bot->getMockedSession()->requestTimeouts));
    });
  }

  public function testUnflaggedHandlerPassesThroughWithoutAction(): void
  {
    // No chat_action flag → handler runs, no sendChatAction calls.
    $this->runAsync(static function (): void {
      $bot = self::makeBot(responses: 0);
      $middleware = self::middleware();
      $event = self::makeMessage(chatId: 9);

      $invoked = false;
      $handler = static function (object $e, array $d) use (&$invoked): string {
        $invoked = true;

        return 'noop';
      };

      $handlerObject = new HandlerObject($handler, [], []); // no flags
      $data = ['bot' => $bot, 'handler' => $handlerObject];

      $result = $middleware($handler, $event, $data);

      self::assertTrue($invoked);
      self::assertSame('noop', $result);
      self::assertCount(0, $bot->getMockedSession()->requestTimeouts);
    });
  }

  public function testNonMessageEventPassesThroughEvenWhenFlagged(): void
  {
    // Non-Message event → no sender, even with flag present.
    $this->runAsync(static function (): void {
      $bot = self::makeBot(responses: 0);
      $middleware = self::middleware();

      // Use a bare Chat object — not a Message.
      $event = new Chat(id: 1, type: 'private');
      $invoked = false;
      $handler = static function (object $e, array $d) use (&$invoked): bool {
        $invoked = true;

        return true;
      };

      $handlerObject = new HandlerObject($handler, [], ['chat_action' => true]);
      $data = ['bot' => $bot, 'handler' => $handlerObject];

      $result = $middleware($handler, $event, $data);

      self::assertTrue($invoked);
      self::assertTrue($result);
      self::assertCount(0, $bot->getMockedSession()->requestTimeouts);
    });
  }

  public function testFlagValueStringUsedAsAction(): void
  {
    // A string flag value is forwarded as the action string.
    $this->runAsync(static function (): void {
      $bot = self::makeBot();
      $middleware = self::middleware(interval: 0.01);
      $event = self::makeMessage(chatId: 1);

      $handler = static fn(object $e, array $d): null => delay(0.03);
      $handlerObject = new HandlerObject($handler, [], ['chat_action' => 'upload_photo']);
      $data = ['bot' => $bot, 'handler' => $handlerObject];

      $middleware($handler, $event, $data);

      $session = $bot->getMockedSession();
      self::assertGreaterThanOrEqual(1, count($session->requestTimeouts));

      $method = $session->getRequest();
      self::assertInstanceOf(SendChatAction::class, $method);
      self::assertSame('upload_photo', $method->action);
    });
  }

  public function testMissingBotInDataPassesThrough(): void
  {
    // If 'bot' key is absent from $data, no sender is started.
    $this->runAsync(static function (): void {
      $middleware = self::middleware();
      $event = self::makeMessage();
      $invoked = false;
      $handler = static function (object $e, array $d) use (&$invoked): bool {
        $invoked = true;

        return true;
      };

      $handlerObject = new HandlerObject($handler, [], ['chat_action' => true]);
      $data = ['handler' => $handlerObject]; // no 'bot'

      $result = $middleware($handler, $event, $data);

      self::assertTrue($invoked);
      self::assertTrue($result);
    });
  }

  public function testAbsentHandlerObjectPassesThrough(): void
  {
    // If 'handler' key is absent or not a HandlerObject, no sender is started.
    $this->runAsync(static function (): void {
      $bot = self::makeBot(responses: 0);
      $middleware = self::middleware();
      $event = self::makeMessage();
      $invoked = false;
      $handler = static function (object $e, array $d) use (&$invoked): bool {
        $invoked = true;

        return true;
      };

      $data = ['bot' => $bot]; // no 'handler'

      $result = $middleware($handler, $event, $data);

      self::assertTrue($invoked);
      self::assertTrue($result);
      self::assertCount(0, $bot->getMockedSession()->requestTimeouts);
    });
  }

  public function testDefaultActionIsTyping(): void
  {
    // When the flag is `true`, the action sent must be 'typing'.
    $this->runAsync(static function (): void {
      $bot = self::makeBot();
      $middleware = self::middleware(interval: 0.01);
      $event = self::makeMessage(chatId: 1);

      $handler = static fn(object $e, array $d): null => delay(0.03);
      $handlerObject = new HandlerObject($handler, [], ['chat_action' => true]);
      $data = ['bot' => $bot, 'handler' => $handlerObject];

      $middleware($handler, $event, $data);

      $method = $bot->getMockedSession()->getRequest();
      self::assertInstanceOf(SendChatAction::class, $method);
      self::assertSame('typing', $method->action);
    });
  }

  public function testMessageThreadIdFromTopicMessage(): void
  {
    // For a topic message (isTopicMessage=true), the thread id must be forwarded.
    $this->runAsync(static function (): void {
      $bot = self::makeBot();
      $middleware = self::middleware(interval: 0.01);
      $event = self::makeMessage(chatId: 5, threadId: 88, isTopic: true);

      $handler = static fn(object $e, array $d): null => delay(0.03);
      $handlerObject = new HandlerObject($handler, [], ['chat_action' => true]);
      $data = ['bot' => $bot, 'handler' => $handlerObject];

      $middleware($handler, $event, $data);

      $method = $bot->getMockedSession()->getRequest();
      self::assertInstanceOf(SendChatAction::class, $method);
      self::assertSame(88, $method->messageThreadId);
    });
  }
}
