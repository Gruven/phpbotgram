<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client\Session\Middleware;

use ArrayAccess;
use Closure;
use Countable;
use OutOfBoundsException;
use RuntimeException;
use WeakMap;

/**
 * @implements ArrayAccess<int, BaseRequestMiddleware>
 */
final class RequestMiddlewareManager implements ArrayAccess, Countable
{
  /** @var list<BaseRequestMiddleware> */
  private array $middlewares = [];

  /**
   * Cached wrapped chain — invalidated on register/unregister. WeakMap keyed
   * by terminal closure: spl_object_id collisions after GC are a real risk if
   * an ephemeral closure is wrapped, so WeakMap (PHP 8+) gives identity-keyed
   * lookup that auto-evicts when the terminal closure is collected.
   *
   * @var WeakMap<Closure, Closure>
   */
  private WeakMap $chainCache;

  public function __construct()
  {
    $this->chainCache = new WeakMap();
  }

  public function register(BaseRequestMiddleware $middleware): BaseRequestMiddleware
  {
    $this->middlewares[] = $middleware;
    $this->chainCache = new WeakMap();

    return $middleware;
  }

  public function unregister(BaseRequestMiddleware $middleware): bool
  {
    foreach ($this->middlewares as $i => $existing) {
      if ($existing === $middleware) {
        array_splice($this->middlewares, $i, 1);
        $this->chainCache = new WeakMap();

        return true;
      }
    }

    return false;
  }

  /**
   * Decorator-factory entry point (mirrors aiogram's MiddlewareManager.__call__).
   * Call `$manager()` to get a registration closure, or `$manager($middleware)`
   * to register inline.
   *
   * @return ($middleware is null ? Closure(BaseRequestMiddleware): BaseRequestMiddleware : BaseRequestMiddleware)
   */
  public function __invoke(?BaseRequestMiddleware $middleware = null): BaseRequestMiddleware|Closure
  {
    if ($middleware !== null) {
      return $this->register($middleware);
    }

    return fn(BaseRequestMiddleware $m): BaseRequestMiddleware => $this->register($m);
  }

  public function count(): int
  {
    return count($this->middlewares);
  }

  public function offsetExists(mixed $offset): bool
  {
    return is_int($offset) && isset($this->middlewares[$offset]);
  }

  /**
   * @throws OutOfBoundsException when offset is non-int or out of range —
   *                              strict by design so callers must guard with offsetExists/isset first.
   */
  public function offsetGet(mixed $offset): BaseRequestMiddleware
  {
    if (!is_int($offset) || !isset($this->middlewares[$offset])) {
      throw new OutOfBoundsException("Middleware offset {$offset} out of bounds");
    }

    return $this->middlewares[$offset];
  }

  public function offsetSet(mixed $offset, mixed $value): void
  {
    throw new RuntimeException('RequestMiddlewareManager is append-only — use register()/unregister()');
  }

  public function offsetUnset(mixed $offset): void
  {
    throw new RuntimeException('RequestMiddlewareManager is append-only — use unregister()');
  }

  public function wrap(Closure $terminal): Closure
  {
    if ($this->middlewares === []) {
      return $terminal;
    }

    if ($this->chainCache->offsetExists($terminal)) {
      return $this->chainCache[$terminal];
    }
    $next = $terminal;

    foreach (array_reverse($this->middlewares) as $middleware) {
      $current = $next;
      $next = static fn(...$args) => $middleware($current, ...$args);
    }

    return $this->chainCache[$terminal] = $next;
  }
}
