<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Webhook;

use function Amp\ByteStream\buffer as bufferStream;

use Amp\Http\Server\Driver\Client;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Socket\InternetAddress;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;
use Closure;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Tests\Support\RecordingDispatcher;
use Gruven\PhpBotGram\Tests\Support\RunAsyncTrait;
use Gruven\PhpBotGram\Webhook\BaseRequestHandler;
use League\Uri\Http as LeagueUri;
use PHPUnit\Framework\TestCase;

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

  public function testResponseBodyIs_Unauthorized_On401(): void
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
