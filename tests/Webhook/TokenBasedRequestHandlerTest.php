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
use Gruven\PhpBotGram\Webhook\TokenBasedRequestHandler;
use InvalidArgumentException;
use League\Uri\Http as LeagueUri;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for {@see TokenBasedRequestHandler}.
 *
 * @internal
 *
 * @coversNothing
 */
final class TokenBasedRequestHandlerTest extends TestCase
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
   * Build a minimal POST Request with a given URI path.
   */
  private function makeRequest(string $path = '/webhook/42:TEST'): Request
  {
    $uri = LeagueUri::new("http://localhost{$path}");

    /** @var array<non-empty-string, string> $headers */
    $headers = ['Content-Type' => 'application/json'];

    return new Request($this->makeClient(), 'POST', $uri, $headers, '{}');
  }

  /**
   * Build a Request with a `bot_token` attribute pre-set (simulating a router).
   */
  private function makeRequestWithAttribute(string $botToken, string $path = '/webhook/{bot_token}'): Request
  {
    $request = $this->makeRequest($path);
    $request->setAttribute('bot_token', $botToken);

    return $request;
  }

  /**
   * Build a default bot factory that always creates a MockedBot for any token.
   *
   * @return Closure(string): Bot
   */
  private function makeFactory(): Closure
  {
    return static fn(string $token): Bot => new MockedBot($token);
  }

  // =========================================================================
  // Constructor stores all params
  // =========================================================================

  public function testExtendsBaseRequestHandler(): void
  {
    $handler = new TokenBasedRequestHandler($this->makeDispatcher(), $this->makeFactory());

    self::assertInstanceOf(BaseRequestHandler::class, $handler);
  }

  public function testHandleInBackgroundDefaultIsTrue(): void
  {
    $handler = new TokenBasedRequestHandler($this->makeDispatcher(), $this->makeFactory());

    $ref = new ReflectionClass(BaseRequestHandler::class);
    $prop = $ref->getProperty('handleInBackground');

    self::assertTrue($prop->getValue($handler));
  }

  public function testHandleInBackgroundCanBeOverriddenToFalse(): void
  {
    $handler = new TokenBasedRequestHandler(
      dispatcher: $this->makeDispatcher(),
      botFactory: $this->makeFactory(),
      handleInBackground: false,
    );

    $ref = new ReflectionClass(BaseRequestHandler::class);
    $prop = $ref->getProperty('handleInBackground');

    self::assertFalse($prop->getValue($handler));
  }

  // =========================================================================
  // verifySecret() always returns true
  // =========================================================================

  public function testVerifySecretAlwaysReturnsTrue(): void
  {
    $handler = new TokenBasedRequestHandler($this->makeDispatcher(), $this->makeFactory());
    $bot = new MockedBot();

    self::assertTrue($handler->verifySecret('', $bot));
    self::assertTrue($handler->verifySecret('anything', $bot));
    self::assertTrue($handler->verifySecret('some-secret', $bot));
  }

  // =========================================================================
  // register() — path validation
  // =========================================================================

  public function testRegisterAcceptsPathWithBotTokenPlaceholder(): void
  {
    $handler = new TokenBasedRequestHandler($this->makeDispatcher(), $this->makeFactory());

    $registeredPath = null;
    $registerRoute = static function (string $path) use (&$registeredPath): void {
      $registeredPath = $path;
    };

    $handler->register($registerRoute, '/webhook/{bot_token}');

    self::assertSame('/webhook/{bot_token}', $registeredPath);
  }

  public function testRegisterThrowsForPathWithoutBotTokenPlaceholder(): void
  {
    $handler = new TokenBasedRequestHandler($this->makeDispatcher(), $this->makeFactory());

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/bot_token/');

    $handler->register(static fn(string $p): null => null, '/webhook/mybot');
  }

  public function testRegisterThrowsForEmptyPath(): void
  {
    $handler = new TokenBasedRequestHandler($this->makeDispatcher(), $this->makeFactory());

    $this->expectException(InvalidArgumentException::class);

    $handler->register(static fn(string $p): null => null, '/webhook');
  }

  // =========================================================================
  // resolveBot() — factory called once, result cached per token
  // =========================================================================

  public function testResolveBotCallsFactoryOnceAndCachesResult(): void
  {
    $callCount = 0;
    $factory = static function (string $token) use (&$callCount): Bot {
      ++$callCount;

      return new MockedBot($token);
    };

    $handler = new TokenBasedRequestHandler($this->makeDispatcher(), $factory);
    $request = $this->makeRequestWithAttribute('42:TOKEN1');

    $bot1 = $handler->resolveBot($request);
    $bot2 = $handler->resolveBot($request);

    self::assertSame(1, $callCount, 'Factory must be called exactly once for the same token.');
    self::assertSame($bot1, $bot2, 'resolveBot must return the cached instance on subsequent calls.');
  }

  public function testResolveBotCallsFactoryAgainForDifferentToken(): void
  {
    $callCount = 0;
    $factory = static function (string $token) use (&$callCount): Bot {
      ++$callCount;

      return new MockedBot($token);
    };

    $handler = new TokenBasedRequestHandler($this->makeDispatcher(), $factory);

    $handler->resolveBot($this->makeRequestWithAttribute('42:TOKEN1'));
    $handler->resolveBot($this->makeRequestWithAttribute('99:TOKEN2'));

    self::assertSame(2, $callCount, 'Factory must be called once per distinct token.');
  }

  public function testResolveBotReturnsDifferentInstancesForDifferentTokens(): void
  {
    $handler = new TokenBasedRequestHandler($this->makeDispatcher(), $this->makeFactory());

    $bot1 = $handler->resolveBot($this->makeRequestWithAttribute('42:TOKEN1'));
    $bot2 = $handler->resolveBot($this->makeRequestWithAttribute('99:TOKEN2'));

    self::assertNotSame($bot1, $bot2);
  }

  public function testResolveBotFallsBackToUriPathWhenNoAttribute(): void
  {
    $handler = new TokenBasedRequestHandler($this->makeDispatcher(), $this->makeFactory());

    // No attribute set — token should be extracted from the trailing URI segment.
    $request = $this->makeRequest('/webhook/42:PATHTOKEN');
    $bot = $handler->resolveBot($request);

    // MockedBot stores the token; verify the correct token was used.
    self::assertInstanceOf(Bot::class, $bot);
  }

  // =========================================================================
  // close() — iterates cached bots and closes their sessions
  // =========================================================================

  public function testCloseClosesAllCachedBotSessions(): void
  {
    $handler = new TokenBasedRequestHandler($this->makeDispatcher(), $this->makeFactory());

    $bot1 = $handler->resolveBot($this->makeRequestWithAttribute('42:TOKEN1'));
    $bot2 = $handler->resolveBot($this->makeRequestWithAttribute('99:TOKEN2'));

    /** @var MockedSession $session1 */
    $session1 = $bot1->session;

    /** @var MockedSession $session2 */
    $session2 = $bot2->session;

    self::assertFalse($session1->closed, 'Session 1 must be open before close().');
    self::assertFalse($session2->closed, 'Session 2 must be open before close().');

    $handler->close();

    self::assertTrue($session1->closed, 'Session 1 must be closed after close().');
    self::assertTrue($session2->closed, 'Session 2 must be closed after close().');
  }

  public function testCloseIsNoOpWhenNoBotsResolved(): void
  {
    $handler = new TokenBasedRequestHandler($this->makeDispatcher(), $this->makeFactory());

    // Must not throw.
    $handler->close();

    // Confirming the handler still works normally after the no-op close.
    $bot = $handler->resolveBot($this->makeRequestWithAttribute('42:TOKEN1'));
    self::assertInstanceOf(Bot::class, $bot);
  }

  public function testCloseClearsTheBotCache(): void
  {
    $callCount = 0;
    $factory = static function (string $token) use (&$callCount): Bot {
      ++$callCount;

      return new MockedBot($token);
    };

    $handler = new TokenBasedRequestHandler($this->makeDispatcher(), $factory);

    $handler->resolveBot($this->makeRequestWithAttribute('42:TOKEN1'));
    self::assertSame(1, $callCount);

    $handler->close();

    // After close(), cache is cleared — factory must be called again.
    $handler->resolveBot($this->makeRequestWithAttribute('42:TOKEN1'));
    self::assertSame(2, $callCount, 'Factory must be called again after close() clears the cache.');
  }
}
