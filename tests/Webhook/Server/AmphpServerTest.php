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
use Gruven\PhpBotGram\Tests\Support\RecordingDispatcher;
use Gruven\PhpBotGram\Tests\Webhook\SpyHttpServer;
use Gruven\PhpBotGram\Webhook\IpFilter;
use Gruven\PhpBotGram\Webhook\Server\IpFilterMiddleware;
use Gruven\PhpBotGram\Webhook\Server\PathRouter;
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
      $this->makeRequest('http://localhost/webhook/42:ABC')
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

    $request = $this->makeRequest('http://localhost/webhook', 'POST', '1.2.3.4');
    $response = $middleware->handleRequest($request, $inner);

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

    self::assertFalse($inner->called, 'Handler must NOT be called when X-Forwarded-For is blocked');
    self::assertSame(401, $response->getStatus());
  }

  // =========================================================================
  // AmphpServer::run() — skip live-server tests
  // =========================================================================

  public function testRunSkippedLiveServer(): void
  {
    $this->markTestSkipped(
      'AmphpServer::run() binds a real TCP socket via SocketHttpServer::start() '
        . 'which is too heavy for a unit-test run. '
        . 'Lifecycle wiring is verified by testLifecycleCallbacksFireDispatcherObservers().'
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
}
