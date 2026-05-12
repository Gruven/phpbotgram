<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher\Middlewares;

use ArrayAccess;
use Closure;
use Countable;
use Gruven\PhpBotGram\Types\TelegramObject;
use OutOfBoundsException;
use RuntimeException;
use WeakMap;

/**
 * Ordered registry of dispatcher-side `BaseMiddleware` instances for a single
 * update type (mirrors aiogram's `aiogram.dispatcher.middlewares.manager.MiddlewareManager`).
 *
 * The manager is owned by a `TelegramEventObserver` (per-update-type observer)
 * and composes the registered middlewares around the terminal handler step in
 * registration order: the first registered middleware is the outermost link
 * of the chain.
 *
 * Shape parallels `Client/Session/Middleware/RequestMiddlewareManager` (same
 * register/unregister/decorator-factory/ArrayAccess/Countable surface) but the
 * chain semantics differ: each link here accepts `(handler, event, data)` and
 * delegates by calling `$handler($event, $data)` — there is no `Bot`/method/
 * timeout positional convention.
 *
 * @implements ArrayAccess<int, BaseMiddleware>
 */
final class MiddlewareManager implements ArrayAccess, Countable
{
  /** @var list<BaseMiddleware> */
  private array $middlewares = [];

  /**
   * Cached wrapped chain keyed by terminal closure. Invalidated on every
   * register/unregister by allocating a fresh `WeakMap`. WeakMap (PHP 8+)
   * gives identity-keyed lookup that auto-evicts when the terminal closure
   * is collected, avoiding spl_object_id collisions when ephemeral closures
   * are wrapped.
   *
   * @var WeakMap<Closure, Closure>
   */
  private WeakMap $chainCache;

  public function __construct()
  {
    $this->chainCache = new WeakMap();
  }

  /**
   * Append a middleware to the chain. Returns the middleware unchanged so the
   * decorator-style call site can keep a reference (parity with upstream
   * `register()` which appends and returns the middleware).
   */
  public function register(BaseMiddleware $middleware): BaseMiddleware
  {
    $this->middlewares[] = $middleware;
    $this->chainCache = new WeakMap();

    return $middleware;
  }

  /**
   * Remove a middleware by identity. Returns `true` if the middleware was
   * removed, `false` if no matching instance was registered. Upstream's
   * `list.remove()` raises `ValueError` on absent items; we return a bool
   * instead so callers can branch without try/catch boilerplate.
   */
  public function unregister(BaseMiddleware $middleware): bool
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
   * Decorator-factory entry point (mirrors aiogram's `MiddlewareManager.__call__`).
   * `$manager($middleware)` registers inline and returns the middleware;
   * `$manager()` returns a registration closure suitable for use as a
   * decorator-like callable.
   *
   * @return ($middleware is null ? Closure(BaseMiddleware): BaseMiddleware : BaseMiddleware)
   */
  public function __invoke(?BaseMiddleware $middleware = null): BaseMiddleware|Closure
  {
    if ($middleware !== null) {
      return $this->register($middleware);
    }

    return fn(BaseMiddleware $m): BaseMiddleware => $this->register($m);
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
   * Strict by design — callers must guard with `offsetExists` / `isset` first.
   *
   * @throws OutOfBoundsException when offset is non-int or out of range.
   */
  public function offsetGet(mixed $offset): BaseMiddleware
  {
    if (!is_int($offset) || !isset($this->middlewares[$offset])) {
      throw new OutOfBoundsException("Middleware offset {$offset} out of bounds");
    }

    return $this->middlewares[$offset];
  }

  public function offsetSet(mixed $offset, mixed $value): void
  {
    throw new RuntimeException('MiddlewareManager is append-only — use register()/unregister()');
  }

  public function offsetUnset(mixed $offset): void
  {
    throw new RuntimeException('MiddlewareManager is append-only — use unregister()');
  }

  /**
   * Compose the registered middlewares around a terminal `(event, data)` step,
   * returning a closure that runs the full chain when invoked.
   *
   * Chain order: middlewares execute in registration order on the way in
   * (first registered runs first) and unwind in reverse on the way out, so
   * for `[A, B, C]` and terminal `T` the call order is
   * `A.before → B.before → C.before → T → C.after → B.after → A.after`.
   *
   * If no middlewares are registered, the terminal is returned unchanged
   * (zero-allocation fast path). Repeated `wrap()` calls with the same
   * terminal hit the WeakMap cache.
   *
   * @param Closure(TelegramObject, array<string, mixed>): mixed $terminal
   *
   * @return Closure(TelegramObject, array<string, mixed>): mixed
   */
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
      $next = static fn(TelegramObject $event, array $data): mixed => $middleware($current, $event, $data);
    }

    return $this->chainCache[$terminal] = $next;
  }
}
