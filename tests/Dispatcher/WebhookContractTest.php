<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Dispatcher;

use function Amp\delay;

use Closure;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Methods\GetMe;
use Gruven\PhpBotGram\Methods\SendMessage;
use Gruven\PhpBotGram\Methods\TelegramMethod;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Tests\Support\RecordingDispatcher;
use Gruven\PhpBotGram\Tests\Support\RunAsyncTrait;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\Update;
use Gruven\PhpBotGram\Types\User;
use PHPUnit\Framework\TestCase;

/**
 * Webhook response contract — Task 3.13.
 *
 * `feedWebhookUpdate` runs the dispatcher chain inside a 55-second budget
 * (configurable per Dispatcher for tests). The contract has two branches:
 *
 * - **In-time**: chain returns before the deadline. If the return value is
 *   a `TelegramMethod`, it bubbles back to the caller (the webhook HTTP
 *   adapter) as the inline response. Anything else (string, sentinel, null)
 *   collapses to `null`.
 * - **Deadline expired**: a `trigger_error("Detected slow response into
 *   webhook…", E_USER_WARNING)` fires and `feedWebhookUpdate` returns
 *   `null` immediately. The dispatch task continues in the background; if
 *   it eventually yields a `TelegramMethod`, that method is routed through
 *   `silentCallRequest` so the side effect still reaches Telegram.
 *
 * `RecordingDispatcher` lets tests assert on the fall-through routing
 * without driving a real HTTP exchange.
 *
 * @internal
 *
 * @coversNothing
 */
final class WebhookContractTest extends TestCase
{
  use RunAsyncTrait;

  protected function tearDown(): void
  {
    Bot::setCurrent(null);
  }

  public function testFastHandlerReturningTelegramMethodIsReturnedInline(): void
  {
    // In-time branch: the handler completes inside the 55s budget AND
    // returns a TelegramMethod. The dispatcher hands the method back so
    // the webhook HTTP adapter can encode it as the inline response body.
    $dispatcher = new Dispatcher();
    $method = new SendMessage(chatId: 1, text: 'hello');
    $dispatcher->message->register(static fn(): SendMessage => $method);

    $result = $this->runAsync(static fn() => $dispatcher->feedWebhookUpdate(
      new MockedBot(),
      self::messageUpdate('hi'),
    ));

    self::assertSame($method, $result);
  }

  public function testFastHandlerReturningStringCollapsesToNull(): void
  {
    // In-time branch but handler returned a non-method value (e.g. a
    // string ack from a logging handler). Spec § "Webhook response
    // contract": only TelegramMethod becomes the inline response;
    // everything else degrades to null so the adapter writes an empty
    // JSON body.
    $dispatcher = new Dispatcher();
    $dispatcher->message->register(static fn(): string => 'logged');

    $result = $this->runAsync(static fn() => $dispatcher->feedWebhookUpdate(
      new MockedBot(),
      self::messageUpdate('hi'),
    ));

    self::assertNull($result);
  }

  public function testNoMatchingHandlerCollapsesToNull(): void
  {
    // No registered handler → propagateEvent returns UNHANDLED. The
    // webhook contract collapses any non-TelegramMethod return (including
    // the sentinel) to null. Distinct from feedUpdate's contract, which
    // surfaces the sentinel verbatim — feedWebhookUpdate is a stricter
    // wrapper because the HTTP adapter has nowhere to put a sentinel.
    $dispatcher = new Dispatcher();

    $result = $this->runAsync(static fn() => $dispatcher->feedWebhookUpdate(
      new MockedBot(),
      self::messageUpdate('hi'),
    ));

    self::assertNull($result);
  }

  public function testSlowHandlerPastDeadlineTriggersWarningAndReturnsNull(): void
  {
    // Deadline-expired branch: handler sleeps past the configurable
    // webhook deadline (tightened to 0.05s here so the test is fast).
    // Two observable effects: feedWebhookUpdate returns null immediately
    // (the caller does NOT block on the slow handler), and a
    // E_USER_WARNING fires with the canonical "Detected slow response"
    // prefix.
    $dispatcher = new Dispatcher(webhookTimeoutSeconds: 0.05);
    $dispatcher->message->register(static function (): string {
      delay(0.2);

      return 'eventually';
    });

    $captured = $this->captureWarnings(function () use ($dispatcher): mixed {
      return $this->runAsync(static fn() => $dispatcher->feedWebhookUpdate(
        new MockedBot(),
        self::messageUpdate('hi'),
      ));
    });

    self::assertNull($captured['result']);
    self::assertNotSame([], $captured['warnings'], 'A slow handler must trigger a warning.');
    self::assertStringContainsString(
      'Detected slow response into webhook',
      $captured['warnings'][0],
    );
  }

  public function testSlowHandlerReturningTelegramMethodRoutesThroughSilentCallRequest(): void
  {
    // Fall-through routing: the handler eventually completes AFTER the
    // deadline AND its return value is a TelegramMethod. The dispatcher
    // must invoke silentCallRequest with that method so the bot still
    // dispatches the side effect (since the HTTP response window is gone).
    //
    // RecordingDispatcher captures the invocation so the test can assert
    // shape without driving a real network call.
    $dispatcher = new RecordingDispatcher(webhookTimeoutSeconds: 0.05);
    $method = new SendMessage(chatId: 1, text: 'after the deadline');
    $dispatcher->message->register(static function () use ($method): SendMessage {
      delay(0.1);

      return $method;
    });
    $bot = new MockedBot();

    $captured = $this->captureWarnings(function () use ($dispatcher, $bot): mixed {
      return $this->runAsync(static function () use ($dispatcher, $bot): mixed {
        $immediate = $dispatcher->feedWebhookUpdate($bot, self::messageUpdate('hi'));
        // Sleep beyond the slow handler so the continuation fires inside
        // the same event loop run — otherwise resetEventLoop() in the
        // RunAsyncTrait tearDown would swap the driver before the
        // task->map callback runs.
        delay(0.2);

        return $immediate;
      });
    });

    self::assertNull($captured['result'], 'Slow path always returns null inline.');
    self::assertCount(1, $dispatcher->silentCalls, 'Method must route through silentCallRequest.');
    self::assertSame($bot, $dispatcher->silentCalls[0][0]);
    self::assertSame($method, $dispatcher->silentCalls[0][1]);
  }

  public function testSlowHandlerReturningNonMethodDoesNotRouteThroughSilentCallRequest(): void
  {
    // Symmetric guard: a slow handler that eventually returns a non-method
    // value MUST NOT invoke silentCallRequest — only TelegramMethod
    // returns route through the fall-through. The warning still fires
    // (slowness is detected regardless of what the handler returns).
    $dispatcher = new RecordingDispatcher(webhookTimeoutSeconds: 0.05);
    $dispatcher->message->register(static function (): string {
      delay(0.1);

      return 'no method here';
    });

    $this->captureWarnings(function () use ($dispatcher): void {
      $this->runAsync(static function () use ($dispatcher): void {
        $dispatcher->feedWebhookUpdate(new MockedBot(), self::messageUpdate('hi'));
        delay(0.2);
      });
    });

    self::assertSame([], $dispatcher->silentCalls);
  }

  public function testRecordingDispatcherSilentCallRequestRecordsButReturnsNull(): void
  {
    // Direct check of the test helper. The RecordingDispatcher overrides
    // silentCallRequest to capture (bot, method) tuples and intentionally
    // returns null — the real Dispatcher returns whatever $bot($method)
    // resolves to, which would hit the MockedSession's canned-response
    // queue and complicate any test that doesn't queue a response.
    $bot = new MockedBot();
    $method = new GetMe();
    $dispatcher = new RecordingDispatcher();

    $result = $dispatcher->silentCallRequest($bot, $method);

    self::assertNull($result);
    self::assertCount(1, $dispatcher->silentCalls);
    self::assertSame($bot, $dispatcher->silentCalls[0][0]);
    self::assertSame($method, $dispatcher->silentCalls[0][1]);
  }

  public function testWebhookTimeoutSecondsConstantMatchesUpstream(): void
  {
    // Upstream pins the deadline at 55 seconds; the port mirrors via a
    // class constant so subclasses (and tests that want the canonical
    // value) can read it without instantiating.
    self::assertSame(55.0, Dispatcher::WEBHOOK_TIMEOUT_SECONDS);
  }

  public function testWebhookTimeoutSecondsDefaultsToConstantWhenNotPassed(): void
  {
    // Constructor argument is optional; when omitted, the effective
    // deadline equals the WEBHOOK_TIMEOUT_SECONDS const. We can't read
    // the private property directly so the assertion is indirect:
    // a fast handler still completes well within 55s, which would fail
    // here only if the default were e.g. 0s.
    $dispatcher = new Dispatcher();
    $dispatcher->message->register(static fn(): string => 'fast');

    $result = $this->runAsync(static fn() => $dispatcher->feedWebhookUpdate(
      new MockedBot(),
      self::messageUpdate('hi'),
    ));

    // No timeout fired → no warning, return value is the non-method
    // string collapsed to null. The key signal is that the test does
    // not hang for 55 real seconds.
    self::assertNull($result);
  }

  public function testFeedWebhookUpdateSwallowsUpdateTypeLookupExceptionWithWarning(): void
  {
    // Fix I1: a forward-compat update kind (Telegram adds a new wire field
    // before the schema is regenerated) surfaces as `UpdateTypeLookupException`
    // inside `feedUpdate`. The webhook adapter must NOT propagate that as a
    // 500 — instead `feedWebhookUpdate` swallows it, emits an `E_USER_WARNING`
    // (parity with upstream's `_listen_update` → `SkipHandler` + RuntimeWarning
    // at `dispatcher.py:267-279`), and returns null so the HTTP adapter writes
    // an empty body.
    //
    // Synthesising the "unknown update type" case: an Update with every
    // optional event slot null causes `Update::eventType()` to return null,
    // which `feedUpdate` translates to `UpdateTypeLookupException`. That's
    // the same error path the framework would hit on a forward-compat type.
    $dispatcher = new Dispatcher();
    $update = new Update(updateId: 99);

    $captured = $this->captureWarnings(function () use ($dispatcher, $update): mixed {
      return $this->runAsync(static fn(): mixed => $dispatcher->feedWebhookUpdate(
        new MockedBot(),
        $update,
      ));
    });

    self::assertNull(
      $captured['result'],
      'feedWebhookUpdate must collapse an UpdateTypeLookupException to null.',
    );
    self::assertNotSame([], $captured['warnings'], 'Unknown update type must trigger a warning.');
    self::assertStringContainsString(
      'feedWebhookUpdate',
      $captured['warnings'][0],
      'Warning must identify the entry point (feedWebhookUpdate).',
    );
    self::assertStringContainsString(
      'unknown update type',
      $captured['warnings'][0],
      'Warning must explain the swallow reason.',
    );
  }

  // -------------------------------------------------------------------------
  // Helpers
  // -------------------------------------------------------------------------

  /**
   * Run $body with a temporary error handler that captures E_USER_WARNING
   * messages. Returns both the body's return value and the list of
   * captured warning messages. Wrapped in try/finally so a failing body
   * cannot leak the handler installation.
   *
   * @template T
   *
   * @param Closure(): T $body
   *
   * @return array{result: T, warnings: list<string>}
   */
  private function captureWarnings(Closure $body): array
  {
    /** @var list<string> $warnings */
    $warnings = [];
    set_error_handler(
      static function (int $errno, string $errstr) use (&$warnings): bool {
        if ($errno === \E_USER_WARNING) {
          $warnings[] = $errstr;

          return true;
        }

        return false;
      },
      \E_USER_WARNING,
    );

    try {
      $result = $body();
    } finally {
      restore_error_handler();
    }

    return ['result' => $result, 'warnings' => $warnings];
  }

  /**
   * Canonical Update fixture — mirrors DispatcherTest's helper of the
   * same name. Kept private+duplicated here so this test file is
   * self-contained.
   */
  private static function messageUpdate(string $text): Update
  {
    $chat = new Chat(id: 1, type: 'private');
    $user = new User(id: 2, isBot: false, firstName: 'Tester');
    $message = new Message(messageId: 1, date: new DateTime('@0'), chat: $chat, fromUser: $user, text: $text);

    return new Update(updateId: 1, message: $message);
  }
}
