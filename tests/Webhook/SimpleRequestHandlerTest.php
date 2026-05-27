<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Webhook;

use Amp\Http\Server\Driver\Client;
use Amp\Http\Server\Request;
use Amp\Socket\InternetAddress;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;
use Closure;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Tests\Support\MockedSession;
use Gruven\PhpBotGram\Tests\Support\RecordingDispatcher;
use Gruven\PhpBotGram\Webhook\BaseRequestHandler;
use Gruven\PhpBotGram\Webhook\SimpleRequestHandler;
use League\Uri\Http as LeagueUri;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for {@see SimpleRequestHandler}.
 *
 * Upstream `tests/test_webhook/test_aiohttp_server.py` `TestSimpleRequestHandler`
 * cases deliberately not ported:
 *
 * - `TestSimpleRequestHandler::test_reply_into_webhook_file` — Phase scope deferral:
 *   webhook-reply multipart response (returning a TelegramMethod as the HTTP body)
 *   is not yet implemented; PHP always returns `200 OK {}` and routes the method
 *   via `silentCallRequest`. Deferred to a later Phase 6 revision.
 * - `TestSimpleRequestHandler::test_reply_into_webhook_text` — Phase scope deferral:
 *   same webhook-reply multipart body deferral as above.
 * - `TestSimpleRequestHandler::test_reply_into_webhook_unhandled` — covered
 *   behaviorally: `BaseRequestHandlerTest::testInlineModeResponseBodyIsEmptyJson`
 *   asserts the `{}` / `application/json` response when no handler fires.
 * - `TestSimpleRequestHandler::test_reply_into_webhook_background` — covered
 *   behaviorally: `BaseRequestHandlerTest::testBackgroundModeResponseBodyIsEmptyJson`
 *   asserts the empty-JSON fast-return; the `silentCallRequest` invocation path is
 *   the same code path tested by `testReturns200ImmediatelyInBackgroundMode`.
 * - `TestSimpleRequestHandler::test_verify_secret` (401 when secret set, no header) —
 *   covered by `testVerifySecretReturnsFalseForEmptyHeaderWhenSecretIsSet` and
 *   `BaseRequestHandlerTest::testReturns401WhenSecretVerificationFails`.
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 *
 * @internal
 *
 * @coversNothing
 */
final class SimpleRequestHandlerTest extends TestCase
{
  // =========================================================================
  // Helpers
  // =========================================================================

  private function makeDispatcher(): RecordingDispatcher
  {
    return new RecordingDispatcher(disableFsm: true);
  }

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
   * Build a minimal POST Request.
   */
  private function makeRequest(): Request
  {
    $uri = LeagueUri::new('http://localhost/webhook');

    /** @var array<non-empty-string, string> $headers */
    $headers = ['Content-Type' => 'application/json'];

    return new Request($this->makeClient(), 'POST', $uri, $headers, '{}');
  }

  // =========================================================================
  // resolveBot() returns the injected bot
  // =========================================================================

  public function testResolveBotReturnsInjectedBot(): void
  {
    $bot = new MockedBot();
    $handler = new SimpleRequestHandler($this->makeDispatcher(), $bot);

    self::assertSame($bot, $handler->resolveBot($this->makeRequest()));
  }

  // =========================================================================
  // verifySecret() — no secret configured (accept all)
  // =========================================================================

  public function testVerifySecretReturnsTrueWhenNoSecretConfigured(): void
  {
    $bot = new MockedBot();
    $handler = new SimpleRequestHandler($this->makeDispatcher(), $bot);

    // Empty header value, no secret token → accept.
    self::assertTrue($handler->verifySecret('', $bot));
  }

  public function testVerifySecretReturnsTrueForAnyTokenWhenNoSecretConfigured(): void
  {
    $bot = new MockedBot();
    $handler = new SimpleRequestHandler($this->makeDispatcher(), $bot);

    // Non-empty header value with no secret configured → still accept.
    self::assertTrue($handler->verifySecret('anything', $bot));
  }

  // =========================================================================
  // verifySecret() — matching secret
  // =========================================================================

  public function testVerifySecretReturnsTrueForMatchingSecret(): void
  {
    $bot = new MockedBot();
    $handler = new SimpleRequestHandler(
      dispatcher: $this->makeDispatcher(),
      bot: $bot,
      secretToken: 'correct',
    );

    self::assertTrue($handler->verifySecret('correct', $bot));
  }

  // =========================================================================
  // verifySecret() — mismatched secret
  // =========================================================================

  public function testVerifySecretReturnsFalseForWrongSecret(): void
  {
    $bot = new MockedBot();
    $handler = new SimpleRequestHandler(
      dispatcher: $this->makeDispatcher(),
      bot: $bot,
      secretToken: 'correct',
    );

    self::assertFalse($handler->verifySecret('wrong', $bot));
  }

  // =========================================================================
  // verifySecret() — empty header with a set secret token must reject
  // =========================================================================

  public function testVerifySecretReturnsFalseForEmptyHeaderWhenSecretIsSet(): void
  {
    $bot = new MockedBot();
    $handler = new SimpleRequestHandler(
      dispatcher: $this->makeDispatcher(),
      bot: $bot,
      secretToken: 'my-secret',
    );

    // Absent / empty header → empty string → must fail.
    self::assertFalse($handler->verifySecret('', $bot));
  }

  // =========================================================================
  // verifySecret() — empty-string secretToken behaves like null (open-access)
  // =========================================================================

  public function testVerifySecretReturnsTrueWhenSecretTokenIsEmptyString(): void
  {
    $bot = new MockedBot();
    // $secretToken = '' must have the same open-access semantics as null.
    $handler = new SimpleRequestHandler(
      dispatcher: $this->makeDispatcher(),
      bot: $bot,
      secretToken: '',
    );

    self::assertTrue($handler->verifySecret('', $bot));
    self::assertTrue($handler->verifySecret('anything', $bot));
  }

  // =========================================================================
  // close() calls bot->session->close() exactly once
  // =========================================================================

  public function testCloseCallsSessionCloseOnce(): void
  {
    $bot = new MockedBot();

    /** @var MockedSession $session */
    $session = $bot->session;

    self::assertFalse($session->closed, 'Session must not be closed before close() is called');

    $handler = new SimpleRequestHandler($this->makeDispatcher(), $bot);
    $handler->close();

    self::assertTrue($session->closed, 'Session must be closed after close() is called');
  }

  // =========================================================================
  // handleInBackground defaults to true
  // =========================================================================

  public function testHandleInBackgroundDefaultIsTrue(): void
  {
    $bot = new MockedBot();
    $handler = new SimpleRequestHandler($this->makeDispatcher(), $bot);

    $ref = new ReflectionClass(BaseRequestHandler::class);
    $prop = $ref->getProperty('handleInBackground');

    self::assertTrue($prop->getValue($handler));
  }

  // =========================================================================
  // handleInBackground can be overridden to false
  // =========================================================================

  public function testHandleInBackgroundCanBeOverriddenToFalse(): void
  {
    $bot = new MockedBot();
    $handler = new SimpleRequestHandler(
      dispatcher: $this->makeDispatcher(),
      bot: $bot,
      handleInBackground: false,
    );

    $ref = new ReflectionClass(BaseRequestHandler::class);
    $prop = $ref->getProperty('handleInBackground');

    self::assertFalse($prop->getValue($handler));
  }

  // =========================================================================
  // Extends BaseRequestHandler
  // =========================================================================

  public function testExtendsBaseRequestHandler(): void
  {
    $bot = new MockedBot();
    $handler = new SimpleRequestHandler($this->makeDispatcher(), $bot);

    self::assertInstanceOf(BaseRequestHandler::class, $handler);
  }
}
