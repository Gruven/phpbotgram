<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Dispatcher\Middlewares;

use Closure;
use Gruven\PhpBotGram\Dispatcher\Event\CancelHandlerException;
use Gruven\PhpBotGram\Dispatcher\Event\RejectedSentinel;
use Gruven\PhpBotGram\Dispatcher\Event\SkipHandlerException;
use Gruven\PhpBotGram\Dispatcher\Event\UnhandledSentinel;
use Gruven\PhpBotGram\Dispatcher\Middlewares\ErrorsMiddleware;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\ErrorEvent;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\Update;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * Ports `aiogram/dispatcher/middlewares/error.py::ErrorsMiddleware`.
 *
 * The middleware sits at the top of the dispatcher chain. It exists to:
 *
 *   1. **Swallow internal signalling exceptions.** Handlers can vote "not for
 *      me" via `SkipHandlerException` (returns `UNHANDLED`) or "stop the chain
 *      cold" via `CancelHandlerException` (returns `REJECTED`). Neither is a
 *      real error, so neither should escape the middleware.
 *
 *   2. **Route real exceptions to the `errors` observer.** Any other
 *      `Throwable` is wrapped in an `ErrorEvent` and forwarded to the
 *      dispatcher's `errors` observer (via the `$errorsTrigger` closure). If
 *      the observer doesn't claim the error (returns `UNHANDLED` / `REJECTED`
 *      / `null`), the original exception is re-raised so the caller sees it.
 *
 * The observer is wired in Task 3.8 (`TelegramEventObserver`). For now the
 * middleware accepts a generic trigger closure with the contract:
 *
 *     fn(string $updateType, ErrorEvent $event, array $data): mixed
 *
 * @internal
 *
 * @coversNothing
 */
final class ErrorsMiddlewareTest extends TestCase
{
  public function testEventUpdateKeyConstantIsCanonical(): void
  {
    // The Dispatcher (Task 3.10) populates `$data['event_update']` before
    // this middleware runs; the constant pins the canonical name so other
    // dispatcher code can refer to it without restringing.
    self::assertSame('event_update', ErrorsMiddleware::EVENT_UPDATE_KEY);
  }

  public function testHappyPathPassesHandlerResultThrough(): void
  {
    $middleware = new ErrorsMiddleware();
    $event = new Chat(id: 1, type: 'private');
    $handler = static fn(object $e, array $d): string => 'ok';

    self::assertSame('ok', $middleware($handler, $event, []));
  }

  public function testSkipHandlerExceptionReturnsUnhandledSentinel(): void
  {
    $middleware = new ErrorsMiddleware();
    $event = new Chat(id: 1, type: 'private');
    $handler = static function (object $e, array $d): never {
      throw new SkipHandlerException('skip');
    };

    self::assertSame(UnhandledSentinel::instance(), $middleware($handler, $event, []));
  }

  public function testCancelHandlerExceptionReturnsRejectedSentinel(): void
  {
    $middleware = new ErrorsMiddleware();
    $event = new Chat(id: 1, type: 'private');
    $handler = static function (object $e, array $d): never {
      throw new CancelHandlerException('cancel');
    };

    self::assertSame(RejectedSentinel::instance(), $middleware($handler, $event, []));
  }

  public function testThrowableWithoutTriggerIsReRaised(): void
  {
    // Defensive path: no observer registered at all. The middleware must
    // re-raise so the caller (Dispatcher loop / test harness) sees the
    // original failure instead of silently eating it.
    $middleware = new ErrorsMiddleware();
    $event = new Chat(id: 1, type: 'private');
    $exception = new RuntimeException('boom');
    $handler = static function (object $e, array $d) use ($exception): never {
      throw $exception;
    };

    try {
      $middleware($handler, $event, [ErrorsMiddleware::EVENT_UPDATE_KEY => self::makeUpdate()]);
      self::fail('Expected RuntimeException to be re-raised');
    } catch (RuntimeException $e) {
      self::assertSame($exception, $e);
    }
  }

  public function testThrowableWithTriggerButMissingEventUpdateIsReRaised(): void
  {
    // The Dispatcher always sets `event_update` before this middleware runs.
    // But under direct invocation (tests, synthetic flows) the key may be
    // missing; we must not swallow the error nor invent an Update — re-raise.
    $triggerCalled = false;
    $trigger = static function (string $type, ErrorEvent $event, array $data) use (&$triggerCalled): mixed {
      $triggerCalled = true;

      return 'should-not-be-reached';
    };

    $middleware = new ErrorsMiddleware($trigger);
    $event = new Chat(id: 1, type: 'private');
    $exception = new RuntimeException('boom');
    $handler = static function (object $e, array $d) use ($exception): never {
      throw $exception;
    };

    try {
      $middleware($handler, $event, []);
      self::fail('Expected RuntimeException to be re-raised');
    } catch (RuntimeException $e) {
      self::assertSame($exception, $e);
    }

    self::assertFalse($triggerCalled, 'Trigger must not be invoked when event_update is missing.');
  }

  public function testThrowableWithTriggerAndEventUpdateInvokesTriggerAndReturnsItsValue(): void
  {
    $update = self::makeUpdate();
    $exception = new RuntimeException('boom');

    $captured = null;
    $trigger = static function (string $type, ErrorEvent $event, array $data) use (&$captured): mixed {
      $captured = ['type' => $type, 'event' => $event, 'data' => $data];

      return 'handled';
    };

    $middleware = new ErrorsMiddleware($trigger);
    $event = new Chat(id: 1, type: 'private');
    $handler = static function (object $e, array $d) use ($exception): never {
      throw $exception;
    };

    $result = $middleware($handler, $event, [
      ErrorsMiddleware::EVENT_UPDATE_KEY => $update,
      'extra' => 'value',
    ]);

    self::assertSame('handled', $result);
    self::assertNotNull($captured);
    self::assertSame('error', $captured['type']);
    self::assertInstanceOf(ErrorEvent::class, $captured['event']);

    /** @var ErrorEvent $errorEvent */
    $errorEvent = $captured['event'];
    self::assertSame($update, $errorEvent->update);
    self::assertSame($exception, $errorEvent->exception);

    // The full data bag (including the event_update entry) must be passed
    // through so the observer's filters can see the same kwargs the handler
    // would have.
    self::assertArrayHasKey(ErrorsMiddleware::EVENT_UPDATE_KEY, $captured['data']);
    self::assertSame($update, $captured['data'][ErrorsMiddleware::EVENT_UPDATE_KEY]);
    self::assertSame('value', $captured['data']['extra']);
  }

  public function testTriggerReturningRejectedSentinelCollapsesToUnhandled(): void
  {
    // Upstream: `if response is REJECTED: return UNHANDLED`. The error chain
    // treats REJECTED-from-errors-observer as "no observer claimed it" so
    // higher-level dispatch logic can fall through to the next router.
    $update = self::makeUpdate();
    $trigger = static fn(string $type, ErrorEvent $event, array $data): mixed => RejectedSentinel::instance();
    $middleware = new ErrorsMiddleware($trigger);
    $event = new Chat(id: 1, type: 'private');
    $handler = static function (object $e, array $d): never {
      throw new RuntimeException('boom');
    };

    self::assertSame(
      UnhandledSentinel::instance(),
      $middleware($handler, $event, [ErrorsMiddleware::EVENT_UPDATE_KEY => $update]),
    );
  }

  public function testTriggerReturningUnhandledSentinelReRaisesOriginal(): void
  {
    $update = self::makeUpdate();
    $exception = new RuntimeException('boom');
    $trigger = static fn(string $type, ErrorEvent $event, array $data): mixed => UnhandledSentinel::instance();
    $middleware = new ErrorsMiddleware($trigger);
    $event = new Chat(id: 1, type: 'private');
    $handler = static function (object $e, array $d) use ($exception): never {
      throw $exception;
    };

    try {
      $middleware($handler, $event, [ErrorsMiddleware::EVENT_UPDATE_KEY => $update]);
      self::fail('Expected RuntimeException to be re-raised when observer returns UNHANDLED');
    } catch (RuntimeException $e) {
      self::assertSame($exception, $e);
    }
  }

  public function testTriggerReturningNullReRaisesOriginal(): void
  {
    // PHP-specific extension: a no-op observer can legitimately return null.
    // Treat null the same as UNHANDLED so the caller sees the original error.
    $update = self::makeUpdate();
    $exception = new RuntimeException('boom');
    $trigger = static fn(string $type, ErrorEvent $event, array $data): mixed => null;
    $middleware = new ErrorsMiddleware($trigger);
    $event = new Chat(id: 1, type: 'private');
    $handler = static function (object $e, array $d) use ($exception): never {
      throw $exception;
    };

    try {
      $middleware($handler, $event, [ErrorsMiddleware::EVENT_UPDATE_KEY => $update]);
      self::fail('Expected RuntimeException to be re-raised when observer returns null');
    } catch (RuntimeException $e) {
      self::assertSame($exception, $e);
    }
  }

  public function testTriggerReturningTruthyValueIsReturnedAsResult(): void
  {
    // The observer claimed the error and produced a substitute value — the
    // middleware should hand that value back as if the handler had returned
    // it cleanly. Use a non-string non-null value to make sure the chain
    // preserves arbitrary types.
    $update = self::makeUpdate();
    $payload = ['recovered' => true, 'message' => 'observer fixed it'];
    $trigger = static fn(string $type, ErrorEvent $event, array $data): mixed => $payload;
    $middleware = new ErrorsMiddleware($trigger);
    $event = new Chat(id: 1, type: 'private');
    $handler = static function (object $e, array $d): never {
      throw new RuntimeException('boom');
    };

    $result = $middleware($handler, $event, [ErrorsMiddleware::EVENT_UPDATE_KEY => $update]);
    self::assertSame($payload, $result);
  }

  public function testEventUpdateThatIsNotAnUpdateInstanceCausesReRaise(): void
  {
    // Defensive: if something poisoned `event_update` with a non-Update
    // value, we cannot construct an ErrorEvent. Treat it as "no Update" and
    // re-raise rather than crash on a TypeError inside ErrorEvent::__construct.
    $triggerCalled = false;
    $trigger = static function (string $type, ErrorEvent $event, array $data) use (&$triggerCalled): mixed {
      $triggerCalled = true;

      return 'should-not-be-reached';
    };
    $middleware = new ErrorsMiddleware($trigger);
    $event = new Chat(id: 1, type: 'private');
    $exception = new RuntimeException('boom');
    $handler = static function (object $e, array $d) use ($exception): never {
      throw $exception;
    };

    try {
      $middleware($handler, $event, [ErrorsMiddleware::EVENT_UPDATE_KEY => 'not-an-update']);
      self::fail('Expected RuntimeException to be re-raised when event_update is not an Update');
    } catch (RuntimeException $e) {
      self::assertSame($exception, $e);
    }

    self::assertFalse($triggerCalled, 'Trigger must not run when event_update is not an Update instance.');
  }

  public function testConstructorAcceptsClosure(): void
  {
    $closure = Closure::fromCallable(static fn(string $t, ErrorEvent $e, array $d): mixed => null);
    $middleware = new ErrorsMiddleware($closure);

    self::assertSame($closure, $middleware->errorsTrigger);
  }

  public function testConstructorDefaultsErrorsTriggerToNull(): void
  {
    $middleware = new ErrorsMiddleware();

    self::assertNull($middleware->errorsTrigger);
  }

  private static function makeUpdate(): Update
  {
    $chat = new Chat(id: 100, type: 'private');
    $message = new Message(messageId: 1, date: new DateTime('@0'), chat: $chat);

    return new Update(updateId: 1, message: $message);
  }
}
