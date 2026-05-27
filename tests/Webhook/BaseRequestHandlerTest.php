<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Webhook;

use Amp\ByteStream\ReadableIterableStream;
use Amp\Http\Server\Driver\Client;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Socket\InternetAddress;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;
use Closure;
use Generator;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Methods\SendMessage;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Tests\Support\RecordingDispatcher;
use Gruven\PhpBotGram\Tests\Support\RunAsyncTrait;
use Gruven\PhpBotGram\Webhook\BaseRequestHandler;
use League\Uri\Http as LeagueUri;
use LogicException;
use PHPUnit\Framework\TestCase;

use function Amp\ByteStream\buffer as bufferStream;
use function Amp\delay;

/**
 * Tests for {@see BaseRequestHandler}.
 *
 * Uses a concrete `FakeRequestHandler` fixture that overrides the three
 * abstract methods with controllable stubs.
 *
 * Construction of `Amp\Http\Server\Request` requires a real
 * `Amp\Http\Server\Driver\Client` instance; we supply an anonymous
 * implementation inline.
 *
 * Upstream `tests/test_webhook/test_aiohttp_server.py` cases deliberately not ported
 * (cases attributable to BaseRequestHandler behavior):
 *
 * - `TestSimpleRequestHandler::test_reply_into_webhook_file` — Phase scope deferral:
 *   webhook-reply multipart response (returning a TelegramMethod as the HTTP body) is
 *   not yet implemented; PHP always returns `200 OK {}` and routes the method via
 *   `silentCallRequest`. Deferred to a later Phase 6 revision.
 * - `TestSimpleRequestHandler::test_reply_into_webhook_text` — Phase scope deferral:
 *   same as above (multipart/form-data webhook-reply body).
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 *
 * @internal
 *
 * @coversNothing
 */
final class BaseRequestHandlerTest extends TestCase
{
  use RunAsyncTrait;

  // =========================================================================
  // Helpers
  // =========================================================================

  /**
   * Build a minimal no-op amphp `Client` stub.
   */
  private function makeClient(): Client
  {
    return new class implements Client {
      public function getId(): int
      {
        return 1;
      }

      public function getRemoteAddress(): SocketAddress
      {
        return new InternetAddress('127.0.0.1', 12345);
      }

      public function getLocalAddress(): SocketAddress
      {
        return new InternetAddress('127.0.0.1', 8080);
      }

      public function getTlsInfo(): ?TlsInfo
      {
        return null;
      }

      public function close(): void {}

      public function isClosed(): bool
      {
        return false;
      }

      public function onClose(Closure $onClose): void {}
    };
  }

  /**
   * Build a POST `Request` with a JSON body.
   *
   * @param array<non-empty-string, string> $headers Extra headers.
   */
  private function makeRequest(
    string $body = '{}',
    array $headers = [],
  ): Request {
    $uri = LeagueUri::new('http://localhost/webhook');

    /** @var array<non-empty-string, string> $base */
    $base = ['Content-Type' => 'application/json'];

    /** @var array<non-empty-string, string> $allHeaders */
    $allHeaders = array_merge($base, $headers);

    return new Request($this->makeClient(), 'POST', $uri, $allHeaders, $body);
  }

  /**
   * Build a POST `Request` whose body is a ReadableStream containing
   * exactly $size bytes.  Unlike the string-body variant, this uses
   * ReadableIterableStream so Payload::buffer() reads through the real
   * streaming path and enforces the size limit, triggering BufferException
   * when $size > MAX_BODY_BYTES.
   *
   * Note: ReadableBuffer is eagerly consumed by Payload's constructor into a
   * plain string (bypassing the limit check), so it cannot be used here.
   */
  private function makeStreamRequest(int $size): Request
  {
    $uri = LeagueUri::new('http://localhost/webhook');

    // Yield the oversized payload as a single generator chunk.
    $body = new ReadableIterableStream((static function () use ($size): Generator {
      yield str_repeat('x', $size);
    })());

    /** @var array<non-empty-string, string> $headers */
    $headers = ['Content-Type' => 'application/json'];

    $request = new Request($this->makeClient(), 'POST', $uri, $headers, '');
    $request->setBody($body);

    return $request;
  }

  /**
   * Build a minimal Telegram `update` JSON payload (message update).
   *
   * @return string JSON-encoded update.
   */
  private function makeUpdatePayload(int $updateId = 1): string
  {
    return json_encode([
      'update_id' => $updateId,
      'message' => [
        'message_id' => 1,
        'date' => 1_000_000,
        'chat' => ['id' => 42, 'type' => 'private'],
        'from' => ['id' => 7, 'is_bot' => false, 'first_name' => 'Alice'],
        'text' => 'hello',
      ],
    ], \JSON_THROW_ON_ERROR);
  }

  /**
   * Build a concrete `BaseRequestHandler` fixture.
   *
   * @param bool $secretOk Return value of `verifySecret`.
   * @param bool $background `$handleInBackground` constructor arg.
   * @param null|Bot $bot Bot returned by `resolveBot` (default: MockedBot).
   */
  private function makeHandler(
    bool $secretOk = true,
    bool $background = false,
    ?Bot $bot = null,
    ?RecordingDispatcher $dispatcher = null,
  ): FakeRequestHandler {
    $bot ??= new MockedBot();
    $dispatcher ??= new RecordingDispatcher(disableFsm: true);

    return new FakeRequestHandler(
      bot: $bot,
      secretOk: $secretOk,
      dispatcher: $dispatcher,
      handleInBackground: $background,
    );
  }

  // =========================================================================
  // Interface contract
  // =========================================================================

  public function testImplementsRequestHandler(): void
  {
    $handler = $this->makeHandler();

    self::assertInstanceOf(RequestHandler::class, $handler);
  }

  public function testImplementsBaseRequestHandler(): void
  {
    $handler = $this->makeHandler();

    self::assertInstanceOf(BaseRequestHandler::class, $handler);
  }

  // =========================================================================
  // Secret-token validation
  // =========================================================================

  public function testReturns401WhenSecretVerificationFails(): void
  {
    $this->runAsync(function (): void {
      $handler = $this->makeHandler(secretOk: false);
      $request = $this->makeRequest(
        body: $this->makeUpdatePayload(),
        headers: ['X-Telegram-Bot-Api-Secret-Token' => 'wrong-token'],
      );

      $response = $handler->handleRequest($request);

      self::assertSame(401, $response->getStatus());
    });
  }

  public function testResponseBodyIsUnauthorizedOn401(): void
  {
    $this->runAsync(function (): void {
      $handler = $this->makeHandler(secretOk: false);
      $response = $handler->handleRequest(
        $this->makeRequest($this->makeUpdatePayload()),
      );

      self::assertSame(401, $response->getStatus());
      self::assertSame('Unauthorized', bufferStream($response->getBody()));
    });
  }

  // =========================================================================
  // Inline dispatch (handleInBackground = false)
  // =========================================================================

  public function testReturns200ForValidRequestInlineMode(): void
  {
    $this->runAsync(function (): void {
      $dispatcher = new RecordingDispatcher(disableFsm: true);
      $handler = $this->makeHandler(
        secretOk: true,
        background: false,
        dispatcher: $dispatcher,
      );

      $response = $handler->handleRequest(
        $this->makeRequest($this->makeUpdatePayload()),
      );

      self::assertSame(200, $response->getStatus());
    });
  }

  public function testInlineModeResponseBodyIsEmptyJson(): void
  {
    $this->runAsync(function (): void {
      $dispatcher = new RecordingDispatcher(disableFsm: true);
      $handler = $this->makeHandler(
        secretOk: true,
        background: false,
        dispatcher: $dispatcher,
      );

      $response = $handler->handleRequest(
        $this->makeRequest($this->makeUpdatePayload()),
      );

      self::assertSame('{}', bufferStream($response->getBody()));
    });
  }

  public function testInlineModeResponseHasJsonContentType(): void
  {
    $this->runAsync(function (): void {
      $dispatcher = new RecordingDispatcher(disableFsm: true);
      $handler = $this->makeHandler(
        secretOk: true,
        background: false,
        dispatcher: $dispatcher,
      );

      $response = $handler->handleRequest(
        $this->makeRequest($this->makeUpdatePayload()),
      );

      self::assertSame('application/json', $response->getHeader('content-type'));
    });
  }

  // =========================================================================
  // Background dispatch (handleInBackground = true)
  // =========================================================================

  public function testReturns200ImmediatelyInBackgroundMode(): void
  {
    $this->runAsync(function (): void {
      $dispatcher = new RecordingDispatcher(disableFsm: true);
      $handler = $this->makeHandler(
        secretOk: true,
        background: true,
        dispatcher: $dispatcher,
      );

      $response = $handler->handleRequest(
        $this->makeRequest($this->makeUpdatePayload()),
      );

      self::assertSame(200, $response->getStatus());
    });
  }

  public function testBackgroundModeResponseBodyIsEmptyJson(): void
  {
    $this->runAsync(function (): void {
      $dispatcher = new RecordingDispatcher(disableFsm: true);
      $handler = $this->makeHandler(
        secretOk: true,
        background: true,
        dispatcher: $dispatcher,
      );

      $response = $handler->handleRequest(
        $this->makeRequest($this->makeUpdatePayload()),
      );

      self::assertSame('{}', bufferStream($response->getBody()));
    });
  }

  // =========================================================================
  // Malformed JSON — 400 Invalid JSON
  // =========================================================================

  /**
   * A body that is not valid JSON must produce 400 in inline mode.
   */
  public function testBaseRequestHandlerReturns400ForMalformedJsonInlineMode(): void
  {
    $this->runAsync(function (): void {
      $handler = $this->makeHandler(secretOk: true, background: false);
      $response = $handler->handleRequest($this->makeRequest('not-valid-json'));

      self::assertSame(400, $response->getStatus());
    });
  }

  /**
   * A body that is not valid JSON must produce 400 in background mode.
   */
  public function testBaseRequestHandlerReturns400ForMalformedJsonBackgroundMode(): void
  {
    $this->runAsync(function (): void {
      $handler = $this->makeHandler(secretOk: true, background: true);
      $response = $handler->handleRequest($this->makeRequest('not-valid-json'));

      self::assertSame(400, $response->getStatus());
    });
  }

  // =========================================================================
  // Non-object JSON body — 400 Invalid JSON body
  // =========================================================================

  /**
   * Valid JSON that is not an object (e.g. a string scalar) must produce 400
   * in inline mode, consistent with the 400-on-invalid-JSON theme.
   *
   * Without this guard the non-array value reaches `feedWebhookUpdate(Bot,
   * array, ...)` and raises a `TypeError` → 500.
   */
  public function testBaseRequestHandlerReturns400ForNonObjectJsonInlineMode(): void
  {
    $this->runAsync(function (): void {
      $handler = $this->makeHandler(secretOk: true, background: false);
      // Valid JSON but not an object — a quoted string scalar.
      $response = $handler->handleRequest($this->makeRequest('"hello"'));

      self::assertSame(400, $response->getStatus());
    });
  }

  /**
   * Same as inline-mode variant but with `handleInBackground = true`.
   */
  public function testBaseRequestHandlerReturns400ForNonObjectJsonBackgroundMode(): void
  {
    $this->runAsync(function (): void {
      $handler = $this->makeHandler(secretOk: true, background: true);
      $response = $handler->handleRequest($this->makeRequest('"hello"'));

      self::assertSame(400, $response->getStatus());
    });
  }

  // =========================================================================
  // Body size limit — 413 Payload Too Large
  // =========================================================================

  /**
   * A body larger than MAX_BODY_BYTES must produce 413 in inline mode.
   */
  public function testBaseRequestHandlerReturns413ForOversizedBodyInlineMode(): void
  {
    $this->runAsync(function (): void {
      $handler = $this->makeHandler(secretOk: true, background: false);
      // One byte over the limit.
      $oversizeBytes = FakeRequestHandler::MAX_BODY_BYTES + 1;
      $request = $this->makeStreamRequest($oversizeBytes);

      $response = $handler->handleRequest($request);

      self::assertSame(413, $response->getStatus());
    });
  }

  /**
   * A body larger than MAX_BODY_BYTES must produce 413 in background mode.
   */
  public function testBaseRequestHandlerReturns413ForOversizedBodyBackgroundMode(): void
  {
    $this->runAsync(function (): void {
      $handler = $this->makeHandler(secretOk: true, background: true);
      $oversizeBytes = FakeRequestHandler::MAX_BODY_BYTES + 1;
      $request = $this->makeStreamRequest($oversizeBytes);

      $response = $handler->handleRequest($request);

      self::assertSame(413, $response->getStatus());
    });
  }

  // =========================================================================
  // resolveBot() is called with the request
  // =========================================================================

  public function testResolveBotIsCalledWithIncomingRequest(): void
  {
    $this->runAsync(function (): void {
      $handler = $this->makeHandler(secretOk: true, background: false);
      $request = $this->makeRequest($this->makeUpdatePayload());

      $handler->handleRequest($request);

      self::assertSame($request, $handler->lastResolveBotRequest);
    });
  }

  // =========================================================================
  // close() contract
  // =========================================================================

  public function testCloseIsIdempotentAndSetsFlag(): void
  {
    $handler = $this->makeHandler();

    self::assertFalse($handler->wasClosed);

    $handler->close();

    self::assertTrue($handler->wasClosed);
  }

  public function testCloseSetsClosedFlag(): void
  {
    $handler = $this->makeHandler();

    self::assertFalse($handler->wasClosed);
    $handler->close();
    self::assertTrue($handler->wasClosed);

    // Second call must not throw.
    $handler->close();
    self::assertTrue($handler->wasClosed);
  }

  // =========================================================================
  // register() helper
  // =========================================================================

  public function testRegisterCallsRouteCallbackWithPathAndSelf(): void
  {
    $handler = $this->makeHandler();

    $registeredPath = null;
    $registeredHandler = null;

    $handler->register(
      static function (string $path, RequestHandler $h) use (&$registeredPath, &$registeredHandler): void {
        $registeredPath = $path;
        $registeredHandler = $h;
      },
      '/my-webhook',
    );

    self::assertSame('/my-webhook', $registeredPath);
    self::assertSame($handler, $registeredHandler);
  }

  // =========================================================================
  // MissingSecretToken header (absent → empty string passed)
  // =========================================================================

  public function testMissingSecretTokenHeaderPassesEmptyStringToVerifySecret(): void
  {
    $this->runAsync(function (): void {
      $handler = $this->makeHandler(secretOk: true);
      // No X-Telegram-Bot-Api-Secret-Token header.
      $handler->handleRequest($this->makeRequest($this->makeUpdatePayload()));

      self::assertSame('', $handler->lastVerifySecretToken);
    });
  }

  public function testPresentSecretTokenHeaderPassesValueToVerifySecret(): void
  {
    $this->runAsync(function (): void {
      $handler = $this->makeHandler(secretOk: true);
      $request = $this->makeRequest(
        body: $this->makeUpdatePayload(),
        headers: ['X-Telegram-Bot-Api-Secret-Token' => 'my-secret'],
      );
      $handler->handleRequest($request);

      self::assertSame('my-secret', $handler->lastVerifySecretToken);
    });
  }

  // =========================================================================
  // awaitBackgroundTasks() — drain in-flight fibers
  // =========================================================================

  /**
   * Verify that awaitBackgroundTasks() blocks until every in-flight background
   * fiber has completed.
   *
   * A handler closure that uses Amp\delay(0.05) simulates nonzero-duration
   * work.  We assert that the closure ran to completion (a flag is set) only
   * after awaitBackgroundTasks() returns — confirming the drain is real.
   */
  public function testAwaitBackgroundTasksDrainsInFlightFibers(): void
  {
    $this->runAsync(function (): void {
      // Use a mutable counter object so PHPStan cannot narrow the type to
      // literal false and raise a staticMethod.impossibleType error.
      $counter = new class {
        public int $completions = 0;
      };

      $dispatcher = new RecordingDispatcher(disableFsm: true);
      $dispatcher->message->register(static function () use ($counter): void {
        delay(0.05);
        $counter->completions++;
      });

      $handler = $this->makeHandler(
        secretOk: true,
        background: true,
        dispatcher: $dispatcher,
      );

      // Send the request — 200 is returned immediately, fiber is in-flight.
      $response = $handler->handleRequest(
        $this->makeRequest($this->makeUpdatePayload()),
      );

      self::assertSame(200, $response->getStatus());
      // Counter must be 0 yet — the fiber is still sleeping.
      self::assertSame(0, $counter->completions, 'Handler must not have run to completion before drain');

      // Drain: block until the background fiber finishes.
      $handler->awaitBackgroundTasks();

      self::assertSame(1, $counter->completions, 'Handler must have run to completion after awaitBackgroundTasks()');
    });
  }

  // =========================================================================
  // awaitBackgroundTasks() — per-fiber failure isolation
  // =========================================================================

  /**
   * Verifies that a single failing background fiber does NOT prevent
   * `awaitBackgroundTasks()` from completing, and that remaining fibers
   * still run to completion.
   *
   * Two background fibers are registered:
   *   1. One that throws `LogicException` (simulates a buggy handler).
   *   2. One that increments a counter (simulates a healthy handler).
   *
   * After draining, the call must return normally (no rethrow), an
   * E_USER_WARNING must have been emitted for the failure, and the
   * counter must have been incremented.
   */
  public function testAwaitBackgroundTasksContinuesWhenSingleFiberThrows(): void
  {
    $this->runAsync(function (): void {
      // Use a mutable counter object so PHPStan cannot narrow to literal.
      $counter = new class {
        public int $completions = 0;
      };

      /** @var list<array{0: int, 1: string}> $warnings */
      $warnings = [];
      set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
        $warnings[] = [$errno, $errstr];

        return true;
      }, \E_USER_WARNING);

      try {
        // Dispatcher 1: always throws from a message handler.
        $throwingDispatcher = new RecordingDispatcher(disableFsm: true);
        $throwingDispatcher->message->register(static function (): void {
          throw new LogicException('simulated fiber failure');
        });

        // Dispatcher 2: increments the counter.
        $countingDispatcher = new RecordingDispatcher(disableFsm: true);
        $countingDispatcher->message->register(static function () use ($counter): void {
          $counter->completions++;
        });

        $throwingHandler = $this->makeHandler(
          secretOk: true,
          background: true,
          dispatcher: $throwingDispatcher,
        );

        $countingHandler = $this->makeHandler(
          secretOk: true,
          background: true,
          dispatcher: $countingDispatcher,
        );

        // Dispatch to both handlers.
        $throwingHandler->handleRequest($this->makeRequest($this->makeUpdatePayload()));
        $countingHandler->handleRequest($this->makeRequest($this->makeUpdatePayload()));

        // Drain — must not throw even though one fiber failed.
        $throwingHandler->awaitBackgroundTasks();
        $countingHandler->awaitBackgroundTasks();
      } finally {
        restore_error_handler();
      }

      // The draining must not have thrown (we reach this line).
      // The healthy fiber must have completed.
      self::assertSame(1, $counter->completions, 'Healthy fiber must have run to completion');

      // A warning must have been emitted for the failing fiber.
      self::assertGreaterThanOrEqual(1, count($warnings), 'At least one E_USER_WARNING must be emitted for the failing fiber');
      self::assertSame(\E_USER_WARNING, $warnings[0][0], 'Warning must be E_USER_WARNING');
      self::assertStringContainsString('simulated fiber failure', $warnings[0][1], 'Warning must contain the exception message');
    });
  }

  // =========================================================================
  // silentCallRequest fall-through — handler-returned TelegramMethod
  // =========================================================================

  /**
   * When feedWebhookUpdate's dispatch chain returns a TelegramMethod in
   * inline (synchronous) mode, BaseRequestHandler must route it via
   * Dispatcher::silentCallRequest rather than embedding it in the HTTP
   * response body.
   *
   * silentCallRequest is now detached into a background fiber so the 200
   * returns immediately; awaitBackgroundTasks() is required before
   * asserting on the recorded calls.
   */
  public function testHandlerReturnedTelegramMethodRoutesViaSilentCallRequest(): void
  {
    $this->runAsync(function (): void {
      $dispatcher = new RecordingDispatcher(disableFsm: true);
      $dispatcher->message->register(
        static fn(): SendMessage => new SendMessage(chatId: 42, text: 'hello'),
      );

      $bot = new MockedBot();
      $handler = $this->makeHandler(
        secretOk: true,
        background: false,
        bot: $bot,
        dispatcher: $dispatcher,
      );

      $handler->handleRequest(
        $this->makeRequest($this->makeUpdatePayload()),
      );

      // silentCallRequest runs in a detached fiber; drain before asserting.
      $handler->awaitBackgroundTasks();

      self::assertCount(1, $dispatcher->silentCalls, 'silentCallRequest must be called exactly once');
      self::assertSame($bot, $dispatcher->silentCalls[0][0], 'silentCallRequest must receive the resolved bot');
      self::assertInstanceOf(SendMessage::class, $dispatcher->silentCalls[0][1], 'silentCallRequest must receive the returned TelegramMethod');
    });
  }

  /**
   * Same as the inline-mode variant, but with handleInBackground=true.
   * The background fiber must also route the returned TelegramMethod via
   * silentCallRequest after awaitBackgroundTasks() drains it.
   */
  public function testHandlerReturnedTelegramMethodRoutesViaSilentCallRequestInBackground(): void
  {
    $this->runAsync(function (): void {
      $dispatcher = new RecordingDispatcher(disableFsm: true);
      $dispatcher->message->register(
        static fn(): SendMessage => new SendMessage(chatId: 42, text: 'hello from background'),
      );

      $bot = new MockedBot();
      $handler = $this->makeHandler(
        secretOk: true,
        background: true,
        bot: $bot,
        dispatcher: $dispatcher,
      );

      $handler->handleRequest(
        $this->makeRequest($this->makeUpdatePayload()),
      );

      // Drain the background fiber so the assertion sees the completed state.
      $handler->awaitBackgroundTasks();

      self::assertCount(1, $dispatcher->silentCalls, 'silentCallRequest must be called exactly once in background mode');
      self::assertSame($bot, $dispatcher->silentCalls[0][0], 'silentCallRequest must receive the resolved bot');
      self::assertInstanceOf(SendMessage::class, $dispatcher->silentCalls[0][1], 'silentCallRequest must receive the returned TelegramMethod');
    });
  }
}

/**
 * Concrete fixture implementing the three abstract stubs.
 *
 * Exposes public state so tests can assert on what was passed to each method.
 *
 * @internal
 */
final class FakeRequestHandler extends BaseRequestHandler
{
  public bool $wasClosed = false;

  public ?Request $lastResolveBotRequest = null;

  public string $lastVerifySecretToken = '';

  public function __construct(
    private readonly Bot $bot,
    private readonly bool $secretOk,
    RecordingDispatcher $dispatcher,
    bool $handleInBackground = false,
  ) {
    parent::__construct(
      dispatcher: $dispatcher,
      handleInBackground: $handleInBackground,
    );
  }

  public function close(): void
  {
    $this->wasClosed = true;
  }

  public function resolveBot(Request $request): Bot
  {
    $this->lastResolveBotRequest = $request;

    return $this->bot;
  }

  public function verifySecret(string $telegramSecretToken, Bot $bot): bool
  {
    $this->lastVerifySecretToken = $telegramSecretToken;

    return $this->secretOk;
  }
}
