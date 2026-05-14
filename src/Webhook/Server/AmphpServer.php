<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Webhook\Server;

use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\HttpServer;
use Amp\Http\Server\Middleware;

use function Amp\Http\Server\Middleware\stackMiddleware;

use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\SocketHttpServer;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Webhook\BaseRequestHandler;
use Gruven\PhpBotGram\Webhook\IpFilter;
use Psr\Log\LoggerInterface as PsrLogger;
use Psr\Log\NullLogger;

/**
 * Stand-alone amphp/http-server v3 boot helper.
 *
 * Port of the `setup_application` + `ip_filter_middleware` pair from
 * `aiogram/webhook/aiohttp_server.py:22-78`.
 *
 * ## Router deviation
 *
 * `amphp/http-server-router` is not installed as a project dependency.
 * `AmphpServer::run()` therefore builds a {@see PathRouter} — a minimal
 * in-house `RequestHandler` that:
 *
 * - Matches an exact path (`'/webhook'`) **or** a single-segment
 *   parameterised path (`'/webhook/{bot_token}'`).
 * - Rejects any request whose path does not match with `404 Not Found`.
 * - Rejects non-POST methods with `405 Method Not Allowed`.
 * - Delegates matching requests to the supplied `BaseRequestHandler`.
 *
 * {@see PathRouter} is intentionally package-private. Callers that need
 * full routing flexibility should compose their own `SocketHttpServer` and
 * call {@see Setup::register()} instead.
 *
 * ## Dispatcher lifecycle wiring
 *
 * `run()` wires `Dispatcher::emitStartup` and `Dispatcher::emitShutdown`
 * to the server's `onStart` / `onStop` callbacks, mirroring upstream's
 * `setup_application` at `aiohttp_server.py:22-40`:
 *
 * ```python
 * app.on_startup.append(on_startup)
 * app.on_shutdown.append(on_shutdown)
 * ```
 *
 * @api
 */
final class AmphpServer
{
  /**
   * Boot an `amphp/http-server` v3 instance with the supplied handler.
   *
   * Steps performed:
   * 1. Build a {@see PathRouter} mapping POST `$path` → `$handler`.
   * 2. Optionally wrap it with {@see IpFilterMiddleware} when `$ipFilter`
   *    is provided (mirrors upstream `ip_filter_middleware`).
   * 3. Create a `SocketHttpServer` via `createForDirectAccess`.
   * 4. Wire `$dispatcher->emitStartup` / `emitShutdown` onto the
   *    server's `onStart` / `onStop` hooks.
   * 5. Call `expose($host:$port)` + `start(handler, errorHandler)` to
   *    bind the listening socket and launch the accept loop.
   * 6. Return the running `SocketHttpServer` so the caller can stop it
   *    and/or await its lifecycle.
   *
   * @param BaseRequestHandler $handler Webhook handler (Tasks 6.2–6.4).
   * @param Dispatcher $dispatcher Dispatcher whose startup/shutdown
   *                               observers are wired to server events.
   * @param string $host Bind host (default `'0.0.0.0'`).
   * @param int $port Listen port (default `8443`).
   * @param string $path URL path, e.g. `'/webhook'` or
   *                     `'/webhook/{bot_token}'`.
   * @param null|IpFilter $ipFilter Optional CIDR allowlist; `null`
   *                                disables IP gating.
   * @param null|PsrLogger $logger PSR-3 logger for the server.
   *                               Defaults to a no-op `NullLogger`.
   * @param array<string,mixed> $workflowData Extra kwargs forwarded to the
   *                                          dispatcher startup/shutdown emitters.
   *
   * @return SocketHttpServer The running server instance.
   */
  public static function run(
    BaseRequestHandler $handler,
    Dispatcher $dispatcher,
    string $host = '0.0.0.0',
    int $port = 8443,
    string $path = '/webhook',
    ?IpFilter $ipFilter = null,
    ?PsrLogger $logger = null,
    array $workflowData = [],
  ): SocketHttpServer {
    $logger ??= new NullLogger();

    // Build the route-matching request handler.
    $routedHandler = new PathRouter($path, $handler);

    // Apply the IP-filter middleware on top of the router when requested.
    /** @var RequestHandler $requestHandler */
    $requestHandler = $ipFilter !== null
        ? stackMiddleware($routedHandler, new IpFilterMiddleware($ipFilter))
        : $routedHandler;

    $server = SocketHttpServer::createForDirectAccess($logger);

    // Wire dispatcher lifecycle observers to the server's start/stop hooks.
    // Mirrors upstream setup_application:
    //   app.on_startup.append(async def on_startup(*a, **kw): await dp.emit_startup(**data))
    //   app.on_shutdown.append(async def on_shutdown(*a, **kw): await dp.emit_shutdown(**data))
    //
    // Merge order matches upstream:
    //   {"dispatcher": dispatcher, **dispatcher.workflow_data, **kwargs}
    // Per-call $workflowData wins on key collision; 'dispatcher' and 'server'
    // are injected so startup/shutdown handlers receive them via named parameters.
    $server->onStart(static function (HttpServer $_) use ($dispatcher, $server, $workflowData): void {
      $dispatcher->emitStartup([
        'dispatcher' => $dispatcher,
        'server' => $server,
        ...$dispatcher->workflowData,
        ...$workflowData,
      ]);
    });

    $server->onStop(static function (HttpServer $_) use ($handler, $dispatcher, $server, $workflowData): void {
      $handler->awaitBackgroundTasks();
      $dispatcher->emitShutdown([
        'dispatcher' => $dispatcher,
        'server' => $server,
        ...$dispatcher->workflowData,
        ...$workflowData,
      ]);
      $handler->close();
    });

    $server->expose("{$host}:{$port}");
    $server->start($requestHandler, new DefaultErrorHandler());

    return $server;
  }
}
