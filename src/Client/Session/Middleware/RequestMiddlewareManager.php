<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client\Session\Middleware;

use Closure;

final class RequestMiddlewareManager
{
  /** @var list<BaseRequestMiddleware> */
  private array $middlewares = [];

  public function register(BaseRequestMiddleware $middleware): BaseRequestMiddleware
  {
    $this->middlewares[] = $middleware;

    return $middleware;
  }

  public function wrap(Closure $terminal): Closure
  {
    $next = $terminal;

    foreach (array_reverse($this->middlewares) as $middleware) {
      $current = $next;
      $next = static fn(...$args) => $middleware($current, ...$args);
    }

    return $next;
  }
}
