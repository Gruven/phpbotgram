<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Webhook;

use Amp\Http\Server\ErrorHandler;
use Amp\Http\Server\HttpServer;
use Amp\Http\Server\HttpServerStatus;
use Amp\Http\Server\RequestHandler;
use Closure;

/**
 * Test-only spy `HttpServer` that records `onStart`/`onStop` callbacks.
 *
 * Implements `HttpServer` so it can be passed to `Setup::register()` and
 * `AmphpServer`-level lifecycle-wiring tests without opening a real socket.
 *
 * The public `$startCallbacks` / `$stopCallbacks` arrays are typed explicitly
 * so PHPStan-level-9 tests can access them without @var downcasts.
 *
 * @internal
 */
final class SpyHttpServer implements HttpServer
{
  /** @var list<Closure(HttpServer):void> */
  public array $startCallbacks = [];

  /** @var list<Closure(HttpServer):void> */
  public array $stopCallbacks = [];

  public function start(RequestHandler $requestHandler, ErrorHandler $errorHandler): void {}

  public function stop(): void {}

  public function onStart(Closure $onStart): void
  {
    $this->startCallbacks[] = $onStart;
  }

  public function onStop(Closure $onStop): void
  {
    $this->stopCallbacks[] = $onStop;
  }

  public function getStatus(): HttpServerStatus
  {
    return HttpServerStatus::Stopped;
  }
}
