<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\CallbackAnswer;

use Closure;
use Gruven\PhpBotGram\Dispatcher\Event\HandlerObject;
use Gruven\PhpBotGram\Dispatcher\Middlewares\BaseMiddleware;
use Gruven\PhpBotGram\Methods\AnswerCallbackQuery;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Types\CallbackQuery;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\User;
use Gruven\PhpBotGram\Utils\CallbackAnswer\CallbackAnswer;
use Gruven\PhpBotGram\Utils\CallbackAnswer\CallbackAnswerMiddleware;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for {@see CallbackAnswerMiddleware}.
 *
 * Verifies that:
 *
 * - Non-CallbackQuery events pass through unchanged.
 * - `$data['callback_answer']` is injected for CallbackQuery events.
 * - Post-mode (default): answer sent AFTER handler.
 * - Pre-mode: answer sent BEFORE handler.
 * - `disabled` flag: answer is skipped entirely.
 * - Per-handler flag overrides middleware default `text`.
 * - Finally-block sends answer even when handler throws.
 * - No double-answer when handler calls `markAnswered()` manually.
 *
 * Port of upstream `tests/test_utils/test_callback_answer.py`.
 *
 * Upstream skips
 * --------------
 * - `TestCallbackAnswerMiddleware::test_construct_answer`: calls the internal
 *   `construct_callback_answer()` as a public method; in PHP it is `private` —
 *   API divergence (a); the logic is exercised indirectly by the integration
 *   tests in this class.
 * - `TestCallbackAnswerMiddleware::test_answer`: calls `middleware.answer()`
 *   as a public method; in PHP it is `private` — API divergence (a).
 * - `TestCallbackAnswerMiddleware::test_call` / `test_invalid_event_type`:
 *   upstream uses `AsyncMock` / `patch` for async coroutine patching; PHP
 *   uses synchronous closures — test infrastructure divergence (c); equivalent
 *   behavior is covered by the integration tests in this class.
 *
 * @internal
 */
final class CallbackAnswerMiddlewareTest extends TestCase
{
  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  private static function makeUser(): User
  {
    return new User(id: 1, isBot: false, firstName: 'Ada');
  }

  private static function makeCallbackQuery(?MockedBot $bot = null): CallbackQuery
  {
    return new CallbackQuery(
      id: 'cq1',
      fromUser: self::makeUser(),
      chatInstance: 'inst',
      bot: $bot,
    );
  }

  private static function makeBot(): MockedBot
  {
    $bot = new MockedBot();
    $bot->addResultFor(AnswerCallbackQuery::class, ok: true, result: true);
    $bot->addResultFor(AnswerCallbackQuery::class, ok: true, result: true);
    $bot->addResultFor(AnswerCallbackQuery::class, ok: true, result: true);

    return $bot;
  }

  // ---------------------------------------------------------------------------
  // Structural
  // ---------------------------------------------------------------------------

  public function testMiddlewareExtendsBaseMiddleware(): void
  {
    self::assertInstanceOf(BaseMiddleware::class, new CallbackAnswerMiddleware());
  }

  // ---------------------------------------------------------------------------
  // Non-CallbackQuery passthrough
  // ---------------------------------------------------------------------------

  public function testNonCallbackQueryEventPassesThroughUnchanged(): void
  {
    $middleware = new CallbackAnswerMiddleware();
    $event = new Chat(id: 1, type: 'private');

    $invoked = false;
    $handler = static function (object $e, array $d) use (&$invoked): string {
      $invoked = true;

      return 'ok';
    };

    $data = [];
    $result = $middleware($handler, $event, $data);

    self::assertTrue($invoked);
    self::assertSame('ok', $result);
    // No callback_answer key injected.
    self::assertArrayNotHasKey('callback_answer', $data);
  }

  // ---------------------------------------------------------------------------
  // Injection of callback_answer
  // ---------------------------------------------------------------------------

  public function testCallbackAnswerIsInjectedIntoData(): void
  {
    $bot = self::makeBot();
    $middleware = new CallbackAnswerMiddleware();
    $event = self::makeCallbackQuery($bot);

    $captured = null;
    $handler = static function (object $e, array $d) use (&$captured): string {
      $captured = $d['callback_answer'] ?? null;

      return 'ok';
    };

    $data = [];
    $middleware($handler, $event, $data);

    self::assertInstanceOf(CallbackAnswer::class, $captured);
  }

  // ---------------------------------------------------------------------------
  // Post-mode: answer sent AFTER handler
  // ---------------------------------------------------------------------------

  public function testPostModeAnswerSentAfterHandler(): void
  {
    $bot = self::makeBot();
    $middleware = new CallbackAnswerMiddleware(pre: false, text: 'Done');
    $event = self::makeCallbackQuery($bot);

    $order = [];
    $handler = static function (object $e, array $d) use (&$order): string {
      $order[] = 'handler';

      return 'result';
    };

    $data = [];
    $result = $middleware($handler, $event, $data);

    $order[] = 'after';

    self::assertSame('result', $result);
    self::assertSame(['handler', 'after'], $order);

    // One API request was made (the post-answer).
    $session = $bot->getMockedSession();
    self::assertCount(1, $session->requestTimeouts);

    $method = $session->getRequest();
    self::assertInstanceOf(AnswerCallbackQuery::class, $method);
    self::assertSame('Done', $method->text);
  }

  // ---------------------------------------------------------------------------
  // Pre-mode: answer sent BEFORE handler
  // ---------------------------------------------------------------------------

  public function testPreModeAnswerSentBeforeHandler(): void
  {
    $bot = self::makeBot();
    $middleware = new CallbackAnswerMiddleware(pre: true);
    $event = self::makeCallbackQuery($bot);

    $requestCountBeforeHandler = 0;
    $handler = static function (object $e, array $d) use (&$requestCountBeforeHandler, $bot): string {
      $requestCountBeforeHandler = count($bot->getMockedSession()->requestTimeouts);

      return 'ok';
    };

    $data = [];
    $middleware($handler, $event, $data);

    // The API call must have been made before the handler ran.
    self::assertSame(1, $requestCountBeforeHandler);
  }

  // ---------------------------------------------------------------------------
  // Disabled flag: answer skipped
  // ---------------------------------------------------------------------------

  public function testDisabledFlagSkipsAnswering(): void
  {
    // Build middleware with disabled=true via per-handler flag.
    $bot = self::makeBot();
    $middleware = new CallbackAnswerMiddleware();
    $event = self::makeCallbackQuery($bot);

    $handler = static fn(object $e, array $d): string => 'ok';
    $handlerObject = new HandlerObject($handler, [], [
      'callback_answer' => ['disabled' => true],
    ]);
    $data = ['handler' => $handlerObject];

    $middleware($handler, $event, $data);

    // No requests should have been made.
    self::assertCount(0, $bot->getMockedSession()->requestTimeouts);
  }

  // ---------------------------------------------------------------------------
  // Per-handler flag overrides middleware default text
  // ---------------------------------------------------------------------------

  public function testHandlerFlagTextOverridesMiddlewareDefault(): void
  {
    $bot = self::makeBot();
    $middleware = new CallbackAnswerMiddleware(text: 'default-text');
    $event = self::makeCallbackQuery($bot);

    $handler = static fn(object $e, array $d): string => 'ok';
    $handlerObject = new HandlerObject($handler, [], [
      'callback_answer' => ['text' => 'override-text'],
    ]);
    $data = ['handler' => $handlerObject];

    $middleware($handler, $event, $data);

    $session = $bot->getMockedSession();
    $method = $session->getRequest();
    self::assertInstanceOf(AnswerCallbackQuery::class, $method);
    self::assertSame('override-text', $method->text);
  }

  public function testHandlerFlagExplicitNullOverridesMiddlewareDefault(): void
  {
    // Per-handler flag of `null` must beat a non-null middleware default.
    // Mirrors upstream Python's `properties.get('text', default)` which
    // returns the stored `None`. Without `array_key_exists`-based merging
    // the explicit null would silently fall back to the middleware default.
    $bot = self::makeBot();
    $middleware = new CallbackAnswerMiddleware(text: 'default-text');
    $event = self::makeCallbackQuery($bot);

    $handler = static fn(object $e, array $d): string => 'ok';
    $handlerObject = new HandlerObject($handler, [], [
      'callback_answer' => ['text' => null],
    ]);
    $data = ['handler' => $handlerObject];

    $middleware($handler, $event, $data);

    $session = $bot->getMockedSession();
    $method = $session->getRequest();
    self::assertInstanceOf(AnswerCallbackQuery::class, $method);
    self::assertNull($method->text, 'Explicit null in flag must reach the answer call');
  }

  // ---------------------------------------------------------------------------
  // Finally: answer sent even when handler throws
  // ---------------------------------------------------------------------------

  public function testAnswerSentEvenWhenHandlerThrows(): void
  {
    $bot = self::makeBot();
    $middleware = new CallbackAnswerMiddleware(pre: false);
    $event = self::makeCallbackQuery($bot);

    $handler = static function (object $e, array $d): never {
      throw new RuntimeException('boom');
    };

    $data = [];

    try {
      $middleware($handler, $event, $data);
    } catch (RuntimeException) {
      // Expected.
    }

    // Despite the exception, the answer must still have been sent.
    self::assertCount(1, $bot->getMockedSession()->requestTimeouts);
  }

  // ---------------------------------------------------------------------------
  // No double-answer: handler manually marks answered
  // ---------------------------------------------------------------------------

  public function testNoDoubleAnswerWhenHandlerMarksAnswered(): void
  {
    $bot = self::makeBot();
    $middleware = new CallbackAnswerMiddleware(pre: false);
    $event = self::makeCallbackQuery($bot);

    $handler = static function (object $e, array $d): string {
      // Handler manually answers and marks the DTO.
      /** @var CallbackAnswer $ca */
      $ca = $d['callback_answer'];

      if ($e instanceof CallbackQuery) {
        $e->answer()->emit();
      }

      $ca->markAnswered();

      return 'ok';
    };

    $data = [];
    $middleware($handler, $event, $data);

    // Only one API call: the manual one inside the handler.
    self::assertCount(1, $bot->getMockedSession()->requestTimeouts);
  }

  // ---------------------------------------------------------------------------
  // Handler flag: pre-mode override
  // ---------------------------------------------------------------------------

  public function testHandlerFlagCanActivatePreMode(): void
  {
    // Middleware defaults to post-mode; flag forces pre.
    $bot = self::makeBot();
    $middleware = new CallbackAnswerMiddleware(pre: false);
    $event = self::makeCallbackQuery($bot);

    $requestCountBeforeHandler = 0;
    $handler = static function (object $e, array $d) use (&$requestCountBeforeHandler, $bot): string {
      $requestCountBeforeHandler = count($bot->getMockedSession()->requestTimeouts);

      return 'ok';
    };

    $handlerObject = new HandlerObject($handler, [], [
      'callback_answer' => ['pre' => true],
    ]);
    $data = ['handler' => $handlerObject];
    $middleware($handler, $event, $data);

    self::assertSame(1, $requestCountBeforeHandler);
  }

  // ---------------------------------------------------------------------------
  // Absent handler object — data has no 'handler' key
  // ---------------------------------------------------------------------------

  public function testAbsentHandlerObjectUsesMiddlewareDefaults(): void
  {
    $bot = self::makeBot();
    $middleware = new CallbackAnswerMiddleware(text: 'fallback');
    $event = self::makeCallbackQuery($bot);

    $handler = static fn(object $e, array $d): string => 'ok';
    $data = []; // no 'handler' key

    $middleware($handler, $event, $data);

    $method = $bot->getMockedSession()->getRequest();
    self::assertInstanceOf(AnswerCallbackQuery::class, $method);
    self::assertSame('fallback', $method->text);
  }

  // ---------------------------------------------------------------------------
  // Middleware returns handler result
  // ---------------------------------------------------------------------------

  public function testMiddlewareReturnsHandlerResult(): void
  {
    $bot = self::makeBot();
    $middleware = new CallbackAnswerMiddleware();
    $event = self::makeCallbackQuery($bot);

    $handler = static fn(object $e, array $d): int => 42;
    $data = [];

    $result = $middleware($handler, $event, $data);

    self::assertSame(42, $result);
  }

  // ---------------------------------------------------------------------------
  // Closure type compatibility
  // ---------------------------------------------------------------------------

  public function testHandlerReceivesClosure(): void
  {
    // Verify the middleware correctly accepts a Closure (not just callable).
    $bot = self::makeBot();
    $middleware = new CallbackAnswerMiddleware();
    $event = self::makeCallbackQuery($bot);

    $capturedEvent = null;
    $handler = static function (object $e, array $d) use (&$capturedEvent): bool {
      $capturedEvent = $e;

      return true;
    };

    $data = [];
    $middleware(Closure::fromCallable($handler), $event, $data);

    self::assertSame($event, $capturedEvent);
  }
}
