<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Webhook;

use Amp\Http\Server\HttpServer;
use Amp\Http\Server\RequestHandler;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;

/**
 * Wires phpbotgram into an **existing** `amphp/http-server` app.
 *
 * Port of `setup_application` from
 * `aiogram/webhook/aiohttp_server.py:22-40`.
 *
 * ```python
 * def setup_application(app, dispatcher, /, **kwargs):
 *     workflow_data = {"app": app, "dispatcher": dispatcher,
 *                      **dispatcher.workflow_data, **kwargs}
 *
 *     async def on_startup(*a, **kw):
 *         await dispatcher.emit_startup(**workflow_data)
 *
 *     async def on_shutdown(*a, **kw):
 *         await dispatcher.emit_shutdown(**workflow_data)
 *
 *     app.on_startup.append(on_startup)
 *     app.on_shutdown.append(on_shutdown)
 * ```
 *
 * ## Router deviation
 *
 * `amphp/http-server-router` is not a project dependency, so `register()`
 * accepts a `callable(string, RequestHandler): void` instead of a concrete
 * `Router` type.  The callable is forwarded to
 * {@see BaseRequestHandler::register()}, which calls it with the path and the
 * handler.  Typical usage with `amphp/http-server-router`:
 *
 * ```php
 * Setup::register(
 *     server: $server,
 *     registerRoute: fn(string $p, RequestHandler $h) => $router->addRoute('POST', $p, $h),
 *     dispatcher: $dispatcher,
 *     handler: $handler,
 *     path: '/webhook',
 * );
 * ```
 *
 * ## Caller owns the server lifecycle
 *
 * `register()` does **not** call `expose()` or `start()`.  The caller retains
 * full control of the `HttpServer` lifecycle — this method only attaches the
 * route and lifecycle hooks.
 *
 * @api
 */
final class Setup
{
  /**
   * Wire a `Dispatcher` + `BaseRequestHandler` into an existing `HttpServer`.
   *
   * - Registers the handler's POST route via `$registerRoute`.
   * - Attaches `$dispatcher->emitStartup()` to `$server->onStart()`.
   * - Attaches `$dispatcher->emitShutdown()` + `$handler->close()` to
   *   `$server->onStop()`.
   *
   * @param HttpServer $server The server to register hooks on.
   * @param callable(string, RequestHandler): void $registerRoute
   *                                                              Callback that registers a POST
   *                                                              route for the given path and handler.
   * @param Dispatcher $dispatcher Dispatcher whose lifecycle observers
   *                               are wired to server start/stop.
   * @param BaseRequestHandler $handler Webhook handler to register.
   * @param string $path URL path (e.g. `'/webhook'`).
   * @param array<string, mixed> $workflowData Extra kwargs forwarded to the
   *                                           dispatcher startup/shutdown emitters.
   */
  public static function register(
    HttpServer $server,
    callable $registerRoute,
    Dispatcher $dispatcher,
    BaseRequestHandler $handler,
    string $path = '/webhook',
    array $workflowData = [],
  ): void {
    // Register the POST route. Delegates to BaseRequestHandler::register()
    // which calls $registerRoute($path, $this).
    $handler->register($registerRoute, $path);

    // Wire dispatcher startup/shutdown observers to the server lifecycle.
    // Mirrors upstream app.on_startup.append / app.on_shutdown.append.
    //
    // Merge order matches upstream setup_application:
    //   workflow_data = {"app": app, "dispatcher": dispatcher,
    //                    **dispatcher.workflow_data, **kwargs}
    // Per-call $workflowData wins on key collision; 'dispatcher' and 'server'
    // are injected as top-level keys so startup/shutdown handlers can receive
    // them via named parameters.
    $server->onStart(static function (HttpServer $srv) use ($dispatcher, $server, $workflowData): void {
      $dispatcher->emitStartup([
        'dispatcher' => $dispatcher,
        'server' => $server,
        ...$dispatcher->workflowData,
        ...$workflowData,
      ]);
    });

    $server->onStop(static function (HttpServer $srv) use ($dispatcher, $handler, $server, $workflowData): void {
      $handler->close();
      $dispatcher->emitShutdown([
        'dispatcher' => $dispatcher,
        'server' => $server,
        ...$dispatcher->workflowData,
        ...$workflowData,
      ]);
    });
  }
}
