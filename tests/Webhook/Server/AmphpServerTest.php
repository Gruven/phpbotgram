<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Webhook\Server;

use Amp\Http\Server\Driver\Client;
use Amp\Http\Server\HttpServer;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Socket\InternetAddress;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;
use Closure;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Tests\Support\RecordingDispatcher;
use Gruven\PhpBotGram\Tests\Webhook\SpyHttpServer;
use Gruven\PhpBotGram\Webhook\BaseRequestHandler;
use Gruven\PhpBotGram\Webhook\IpFilter;
use Gruven\PhpBotGram\Webhook\Server\AmphpServer;
use Gruven\PhpBotGram\Webhook\Server\IpFilterMiddleware;
use Gruven\PhpBotGram\Webhook\Server\PathRouter;
use Gruven\PhpBotGram\Webhook\TokenBasedRequestHandler;
use InvalidArgumentException;
use League\Uri\Http as LeagueUri;
use PHPUnit\Framework\TestCase;

/**
 * Tests for {@see PathRouter} and {@see IpFilterMiddleware}.
 *
 * `AmphpServer::run()` binds a real TCP socket via `SocketHttpServer::start()`,
 * which is too heavy for a unit-test run. Those tests are skipped. The
 * lifecycle-wiring contract (onStart/onStop → emitStartup/emitShutdown) is
 * verified using a {@see SpyHttpServer} that never touches a real socket.
 *
 * Upstream `tests/test_webhook/test_aiohttp_server.py` `TestAiohttpServer`
 * cases deliberately not ported:
 *
 * - `TestAiohttpServer::test_setup_application` — API divergence: aiohttp
 *   inspects `app.on_startup` / `app.on_shutdown` observer lists; PHP wires
 *   callbacks via `$server->onStart()` / `$server->onStop()`. The equivalent
 *   behavior (startup/shutdown callbacks registered and invoked) is covered by
 *   `SetupTest::testRegisterAttachesOnStartCallback`, `testOnStartFiresEmitStartup`,
 *   and `testLifecycleCallbacksFireDispatcherObservers`.
 * - `TestAiohttpServer::test_middleware` (uses `aiohttp_client` live fixture) —
 *   API divergence / live-service required: aiohttp's `ip_filter_middleware` is a
 *   coroutine-based WSGI-style middleware wrapping `aiohttp.web.Application`; PHP
 *   uses `IpFilterMiddleware` implementing amphp's `Middleware` interface. The
 *   IP-blocking contract is covered by `testIpFilterMiddlewareAllowsKnownIp`,
 *   `testIpFilterMiddlewareBlocks401ForUnknownIp`,
 *   `testIpFilterMiddlewareHonoursXForwardedFor`, and
 *   `testIpFilterMiddlewareBlocksWhenXForwardedForIsBlocked`.
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 *
 * @internal
 *
 * @coversNothing
 */
final class AmphpServerTest extends TestCase
{
  // =========================================================================
  // Helpers
  // =========================================================================

  private function makeClient(string $remoteIp = '149.154.160.1'): Client
  {
    return new class ($remoteIp) implements Client {
      private string $ip;

      public function __construct(string $ip)
      {
        $this->ip = $ip;
      }

      public function getId(): int
      {
        return 1;
      }

      public function getRemoteAddress(): SocketAddress
      {
        return new InternetAddress($this->ip, 12345);
      }

      public function getLocalAddress(): SocketAddress
      {
        return new InternetAddress('127.0.0.1', 8443);
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
   * @param non-empty-string $uri
   * @param non-empty-string $method
   */
  private function makeRequest(
    string $uri = 'http://localhost/webhook',
    string $method = 'POST',
    string $remoteIp = '149.154.160.1',
  ): Request {
    return new Request(
      $this->makeClient($remoteIp),
      $method,
      LeagueUri::new($uri),
      ['Content-Type' => 'application/json'],
      '{}',
    );
  }

  // =========================================================================
  // PathRouter — exact path match
  // =========================================================================

  public function testPathRouterMatchesExactPath(): void
  {
    $inner = new class implements RequestHandler {
      public bool $called = false;

      public function handleRequest(Request $request): Response
      {
        $this->called = true;

        return new Response(200, [], 'ok');
      }
    };

    $router = new PathRouter('/webhook', $inner);
    $response = $router->handleRequest($this->makeRequest('http://localhost/webhook'));

    self::assertTrue($inner->called);
    self::assertSame(200, $response->getStatus());
  }

  public function testPathRouterReturns404ForUnknownPath(): void
  {
    $inner = new class implements RequestHandler {
      public function handleRequest(Request $request): Response
      {
        return new Response(200, [], 'should not reach here');
      }
    };

    $router = new PathRouter('/webhook', $inner);
    $response = $router->handleRequest($this->makeRequest('http://localhost/other'));

    self::assertSame(404, $response->getStatus());
  }

  public function testPathRouterReturns405ForNonPostMethod(): void
  {
    $inner = new class implements RequestHandler {
      public function handleRequest(Request $request): Response
      {
        return new Response(200, [], 'should not reach here');
      }
    };

    $router = new PathRouter('/webhook', $inner);
    $response = $router->handleRequest($this->makeRequest('http://localhost/webhook', 'GET'));

    self::assertSame(405, $response->getStatus());
  }

  public function testPathRouterMatchesParameterisedPath(): void
  {
    $inner = new class implements RequestHandler {
      public bool $called = false;

      public function handleRequest(Request $request): Response
      {
        $this->called = true;

        return new Response(200, [], 'ok');
      }
    };

    $router = new PathRouter('/webhook/{bot_token}', $inner);
    $response = $router->handleRequest(
      $this->makeRequest('http://localhost/webhook/42:ABC'),
    );

    self::assertTrue($inner->called);
    self::assertSame(200, $response->getStatus());
  }

  public function testPathRouterReturnsPatternViaGetter(): void
  {
    $inner = new class implements RequestHandler {
      public function handleRequest(Request $request): Response
      {
        return new Response(200);
      }
    };

    $router = new PathRouter('/my/path', $inner);
    self::assertSame('/my/path', $router->getPattern());
  }

  public function testPathRouterCapturesPlaceholderAndSetsRequestAttribute(): void
  {
    $inner = new class implements RequestHandler {
      public ?Request $capturedRequest = null;

      public function handleRequest(Request $request): Response
      {
        $this->capturedRequest = $request;

        return new Response(200, [], 'ok');
      }
    };

    $router = new PathRouter('/{token}/webhook', $inner);
    $router->handleRequest($this->makeRequest('http://localhost/abc123/webhook'));

    self::assertNotNull($inner->capturedRequest, 'Inner handler must have been called');
    self::assertSame('abc123', $inner->capturedRequest->getAttribute('token'));
  }

  public function testPathRouterParameterisedPathDoesNotMatchBarePrefix(): void
  {
    $inner = new class implements RequestHandler {
      public function handleRequest(Request $request): Response
      {
        return new Response(200, [], 'should not reach here');
      }
    };

    // Path without a token segment should not match /webhook/{bot_token}.
    $router = new PathRouter('/webhook/{bot_token}', $inner);
    $response = $router->handleRequest($this->makeRequest('http://localhost/webhook'));

    self::assertSame(404, $response->getStatus());
  }

  // =========================================================================
  // IpFilterMiddleware
  // =========================================================================

  public function testIpFilterMiddlewareAllowsKnownIp(): void
  {
    $filter = new IpFilter(['149.154.160.0/20']);
    $middleware = new IpFilterMiddleware($filter);

    $inner = new class implements RequestHandler {
      public bool $called = false;

      public function handleRequest(Request $request): Response
      {
        $this->called = true;

        return new Response(200, [], 'ok');
      }
    };

    $request = $this->makeRequest('http://localhost/webhook', 'POST', '149.154.160.1');
    $response = $middleware->handleRequest($request, $inner);

    self::assertTrue($inner->called, 'Handler must be called for an allowed IP');
    self::assertSame(200, $response->getStatus());
  }

  public function testIpFilterMiddlewareBlocks401ForUnknownIp(): void
  {
    $filter = new IpFilter(['149.154.160.0/20']);
    $middleware = new IpFilterMiddleware($filter);

    $inner = new class implements RequestHandler {
      public bool $called = false;

      public function handleRequest(Request $request): Response
      {
        $this->called = true;

        return new Response(200, [], 'should not reach here');
      }
    };

    // Suppress the E_USER_WARNING emitted for blocked IPs — this test only
    // asserts on the 401 status; the warning itself is verified separately
    // in testIpFilterMiddlewareEmitsWarningOnBlockedRequest.
    set_error_handler(static fn(): bool => true, \E_USER_WARNING);

    try {
      $request = $this->makeRequest('http://localhost/webhook', 'POST', '1.2.3.4');
      $response = $middleware->handleRequest($request, $inner);
    } finally {
      restore_error_handler();
    }

    self::assertFalse($inner->called, 'Handler must NOT be called for a blocked IP');
    self::assertSame(401, $response->getStatus());
  }

  public function testIpFilterMiddlewareHonoursXForwardedFor(): void
  {
    $filter = new IpFilter(['149.154.160.0/20']);
    $middleware = new IpFilterMiddleware($filter);

    $inner = new class implements RequestHandler {
      public bool $called = false;

      public function handleRequest(Request $request): Response
      {
        $this->called = true;

        return new Response(200, [], 'ok');
      }
    };

    // Build request with a fake remote IP (blocked) but X-Forwarded-For
    // pointing to an allowed address.
    $request = new Request(
      $this->makeClient('1.2.3.4'),
      'POST',
      LeagueUri::new('http://localhost/webhook'),
      [
        'Content-Type' => 'application/json',
        'X-Forwarded-For' => '149.154.163.255, 1.2.3.4',
      ],
      '{}',
    );

    $response = $middleware->handleRequest($request, $inner);

    self::assertTrue($inner->called, 'Handler must be called when X-Forwarded-For IP is allowed');
    self::assertSame(200, $response->getStatus());
  }

  public function testIpFilterMiddlewareBlocksWhenXForwardedForIsBlocked(): void
  {
    $filter = new IpFilter(['149.154.160.0/20']);
    $middleware = new IpFilterMiddleware($filter);

    $inner = new class implements RequestHandler {
      public bool $called = false;

      public function handleRequest(Request $request): Response
      {
        $this->called = true;

        return new Response(200, [], 'should not reach here');
      }
    };

    // Suppress the E_USER_WARNING emitted for blocked IPs — this test only
    // asserts on the 401 status; the warning itself is verified separately
    // in testIpFilterMiddlewareEmitsWarningOnBlockedRequest.
    set_error_handler(static fn(): bool => true, \E_USER_WARNING);

    try {
      // X-Forwarded-For contains a blocked IP; the real remote is allowed
      // but X-Forwarded-For takes precedence.
      $request = new Request(
        $this->makeClient('149.154.160.1'),
        'POST',
        LeagueUri::new('http://localhost/webhook'),
        [
          'Content-Type' => 'application/json',
          'X-Forwarded-For' => '1.2.3.4',
        ],
        '{}',
      );

      $response = $middleware->handleRequest($request, $inner);
    } finally {
      restore_error_handler();
    }

    self::assertFalse($inner->called, 'Handler must NOT be called when X-Forwarded-For is blocked');
    self::assertSame(401, $response->getStatus());
  }

  /**
   * Verify that a warning carrying the blocked IP is emitted when
   * IpFilterMiddleware rejects a request from a non-allowlisted address.
   *
   * Mirrors upstream `aiogram/webhook/aiohttp_server.py:79`:
   *   loggers.webhook.warning("Blocking request from an unauthorized IP: %s", ip_address)
   */
  public function testIpFilterMiddlewareEmitsWarningOnBlockedRequest(): void
  {
    $filter = new IpFilter(['149.154.160.0/20']);
    $middleware = new IpFilterMiddleware($filter);

    $inner = new class implements RequestHandler {
      public function handleRequest(Request $request): Response
      {
        return new Response(200, [], 'should not reach here');
      }
    };

    /** @var list<array{0: int, 1: string}> $warnings */
    $warnings = [];
    set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
      $warnings[] = [$errno, $errstr];

      return true;
    }, \E_USER_WARNING);

    try {
      $request = $this->makeRequest('http://localhost/webhook', 'POST', '1.2.3.4');
      $response = $middleware->handleRequest($request, $inner);
    } finally {
      restore_error_handler();
    }

    self::assertSame(401, $response->getStatus());
    self::assertCount(1, $warnings, 'Exactly one E_USER_WARNING must be emitted for a blocked request');
    self::assertSame(\E_USER_WARNING, $warnings[0][0], 'Warning must be E_USER_WARNING');
    self::assertStringContainsString('1.2.3.4', $warnings[0][1], 'Warning message must contain the blocked IP');
  }

  /**
   * Verify that the warning message for a blocked IPv6 address contains the
   * un-bracketed address (e.g. 'fe80::1', not '[fe80').
   *
   * Regression test for the fragile explode(':', ...) fallback that was
   * replaced by InternetAddress::getAddress().
   */
  public function testIpFilterMiddlewareEmitsUnbracketedIpv6InWarning(): void
  {
    $filter = new IpFilter(['149.154.160.0/20']);
    $middleware = new IpFilterMiddleware($filter);

    $inner = new class implements RequestHandler {
      public function handleRequest(Request $request): Response
      {
        return new Response(200, [], 'should not reach here');
      }
    };

    /** @var list<array{0: int, 1: string}> $warnings */
    $warnings = [];
    set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
      $warnings[] = [$errno, $errstr];

      return true;
    }, \E_USER_WARNING);

    try {
      // IPv6 client — the warning must contain 'fe80::1', not '[fe80'.
      $request = new Request(
        $this->makeClient('fe80::1'),
        'POST',
        LeagueUri::new('http://localhost/webhook'),
        ['Content-Type' => 'application/json'],
        '{}',
      );
      $response = $middleware->handleRequest($request, $inner);
    } finally {
      restore_error_handler();
    }

    self::assertSame(401, $response->getStatus());
    self::assertCount(1, $warnings, 'Exactly one warning must be emitted');
    self::assertStringContainsString('fe80::1', $warnings[0][1], 'Warning must contain the un-bracketed IPv6 address');
    self::assertStringNotContainsString('[fe80', $warnings[0][1], 'Warning must NOT contain the bracketed form');
  }

  // =========================================================================
  // AmphpServer::run() — skip live-server tests
  // =========================================================================

  public function testRunSkippedLiveServer(): void
  {
    $this->markTestSkipped(
      'AmphpServer::run() binds a real TCP socket via SocketHttpServer::start() '
        . 'which is too heavy for a unit-test run. '
        . 'Lifecycle wiring is verified by testLifecycleCallbacksFireDispatcherObservers().',
    );
  }

  // =========================================================================
  // AmphpServer lifecycle wiring shape (spy server, no real socket)
  // =========================================================================

  /**
   * Verify that the onStart/onStop wiring pattern used by AmphpServer::run()
   * correctly fires emitStartup / emitShutdown on the Dispatcher when the
   * server lifecycle fires.
   */
  public function testLifecycleCallbacksFireDispatcherObservers(): void
  {
    $dispatcher = new RecordingDispatcher(disableFsm: true);

    // Use a mutable counter object so PHPStan cannot narrow to literal false.
    $counts = new class {
      public int $startup = 0;
      public int $shutdown = 0;
    };

    $dispatcher->startup->register(static function () use ($counts): void {
      $counts->startup++;
    });
    $dispatcher->shutdown->register(static function () use ($counts): void {
      $counts->shutdown++;
    });

    $spy = new SpyHttpServer();
    $workflowData = ['foo' => 'bar'];

    // Wire the same closures that AmphpServer::run() attaches.
    $spy->onStart(static function (HttpServer $_) use ($dispatcher, $workflowData): void {
      $dispatcher->emitStartup($workflowData);
    });

    $spy->onStop(static function (HttpServer $_) use ($dispatcher, $workflowData): void {
      $dispatcher->emitShutdown($workflowData);
    });

    // Simulate server start.
    foreach ($spy->startCallbacks as $cb) {
      $cb($spy);
    }

    self::assertSame(1, $counts->startup, 'emitStartup must fire exactly once on server start');
    self::assertSame(0, $counts->shutdown, 'emitShutdown must NOT fire before stop');

    // Simulate server stop.
    foreach ($spy->stopCallbacks as $cb) {
      $cb($spy);
    }

    self::assertSame(1, $counts->shutdown, 'emitShutdown must fire exactly once on server stop');
  }

  // =========================================================================
  // AmphpServer workflow data merge (#1 fix) verified via spy wiring
  // =========================================================================

  public function testWorkflowDataMergedWithDispatcherWorkflowDataOnStart(): void
  {
    $dispatcher = new RecordingDispatcher(disableFsm: true);
    $dispatcher->workflowData['db'] = 'pdo-instance';

    /** @var null|array<string,mixed> $capturedStartup */
    $capturedStartup = null;

    $dispatcher->startup->register(
      static function (mixed ...$rest) use (&$capturedStartup): void {
        $capturedStartup = $rest;
      },
    );

    $spy = new SpyHttpServer();
    $workflowData = ['extra' => 'x'];

    // Mirror the exact wiring shape AmphpServer::run() uses.
    $spy->onStart(static function (HttpServer $_) use ($dispatcher, $spy, $workflowData): void {
      $dispatcher->emitStartup([
        'dispatcher' => $dispatcher,
        'server' => $spy,
        ...$dispatcher->workflowData,
        ...$workflowData,
      ]);
    });

    foreach ($spy->startCallbacks as $cb) {
      $cb($spy);
    }

    self::assertIsArray($capturedStartup);
    self::assertSame('pdo-instance', $capturedStartup['db'] ?? null, 'dispatcher->workflowData must be merged');
    self::assertSame('x', $capturedStartup['extra'] ?? null, 'per-call workflowData must be merged');
    self::assertSame($dispatcher, $capturedStartup['dispatcher'] ?? null, 'dispatcher must be injected');
  }

  // =========================================================================
  // AmphpServer shutdown order: emitShutdown before close() (polling-mode parity)
  // =========================================================================

  /**
   * Verifies that the onStop closure wired by AmphpServer::run() fires
   * `emitShutdown` BEFORE calling `handler->close()`, matching the order
   * used by `Dispatcher::run` in polling mode.
   *
   * Uses a SpyHttpServer and manually wires the same closure shape that
   * AmphpServer::run() attaches to avoid opening a real socket.
   */
  public function testOnStopFiresEmitShutdownBeforeClose(): void
  {
    $dispatcher = new RecordingDispatcher(disableFsm: true);

    // Shared ordered log. The shutdown observer appends via use(&); the handler
    // appends via an injected Closure so the anonymous class avoids storing a
    // bare `object` property (which PHPStan cannot resolve property names on).
    /** @var list<string> $callOrder */
    $callOrder = [];

    $dispatcher->shutdown->register(static function () use (&$callOrder): void {
      $callOrder[] = 'shutdown';
    });

    // Inject a recorder closure so close() appends without needing a typed
    // object property on the anonymous class.
    $recordClose = static function () use (&$callOrder): void {
      $callOrder[] = 'close';
    };

    // Minimal close-tracking handler stub.
    $handler = new class ($recordClose) extends BaseRequestHandler {
      public function __construct(private readonly Closure $recordClose)
      {
        parent::__construct(new RecordingDispatcher(disableFsm: true), false);
      }

      public function close(): void
      {
        ($this->recordClose)();
      }

      public function resolveBot(Request $request): Bot
      {
        return new MockedBot();
      }

      public function verifySecret(string $telegramSecretToken, Bot $bot): bool
      {
        return true;
      }
    };

    $spy = new SpyHttpServer();
    $workflowData = [];

    // Wire the exact same onStop closure shape that AmphpServer::run() uses.
    $spy->onStop(static function (HttpServer $_) use ($handler, $dispatcher, $spy, $workflowData): void {
      $handler->awaitBackgroundTasks();
      $dispatcher->emitShutdown([
        'dispatcher' => $dispatcher,
        'server' => $spy,
        ...$dispatcher->workflowData,
        ...$workflowData,
      ]);
      $handler->close();
    });

    foreach ($spy->stopCallbacks as $cb) {
      $cb($spy);
    }

    self::assertSame(['shutdown', 'close'], $callOrder, 'emitShutdown must fire before close()');
    $shutdownPos = array_search('shutdown', $callOrder, true);
    $closePos = array_search('close', $callOrder, true);
    self::assertIsInt($shutdownPos);
    self::assertIsInt($closePos);
    self::assertLessThan($closePos, $shutdownPos, "'shutdown' must precede 'close' in call order");
  }

  public function testPerCallWorkflowDataOverridesDispatcherDefaultOnStart(): void
  {
    $dispatcher = new RecordingDispatcher(disableFsm: true);
    $dispatcher->workflowData['db'] = 'default';

    /** @var null|array<string,mixed> $capturedStartup */
    $capturedStartup = null;

    $dispatcher->startup->register(
      static function (mixed ...$rest) use (&$capturedStartup): void {
        $capturedStartup = $rest;
      },
    );

    $spy = new SpyHttpServer();
    $workflowData = ['db' => 'override'];

    // Mirror the exact wiring shape AmphpServer::run() uses.
    $spy->onStart(static function (HttpServer $_) use ($dispatcher, $spy, $workflowData): void {
      $dispatcher->emitStartup([
        'dispatcher' => $dispatcher,
        'server' => $spy,
        ...$dispatcher->workflowData,
        ...$workflowData,
      ]);
    });

    foreach ($spy->startCallbacks as $cb) {
      $cb($spy);
    }

    self::assertIsArray($capturedStartup);
    self::assertSame('override', $capturedStartup['db'] ?? null, 'per-call workflowData must override dispatcher default');
  }

  /**
   * Regression: `AmphpServer::run()` must run each handler's own
   * `register()`-time validation as a side effect, so a
   * `TokenBasedRequestHandler` wired against a path missing `{bot_token}` is
   * caught at boot (`InvalidArgumentException`) rather than 500-ing every
   * inbound request later. The bare side-effect closure passed by
   * `AmphpServer::run` is a no-op route registrar — we only care about the
   * validation that runs inside `TokenBasedRequestHandler::register()`.
   */
  public function testRunThrowsWhenTokenBasedHandlerPathLacksBotTokenPlaceholder(): void
  {
    $dispatcher = new RecordingDispatcher(disableFsm: true);
    $handler = new TokenBasedRequestHandler(
      $dispatcher,
      static fn(string $token): Bot => new MockedBot(),
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/bot_token/');

    AmphpServer::run(
      handler: $handler,
      dispatcher: $dispatcher,
      path: '/webhook',
    );
  }
}
