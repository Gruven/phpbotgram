<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher\Event;

use Closure;

/**
 * Simple events observer used for managing events that are **not** related to
 * Telegram updates — primarily startup/shutdown lifecycle hooks (mirrors
 * upstream `aiogram.dispatcher.event.event.EventObserver`). Update routing is
 * handled separately by `TelegramEventObserver` (filters + middlewares +
 * handler results), which is intentionally a different class.
 *
 * Handlers are wrapped in `CallableObject` at registration time, so reflection
 * runs **once** per handler instead of per `trigger()` call. The dispatcher
 * passes a kwargs bag (`bot`, `event_context`, `state`, …) and each handler
 * declares only the keys it cares about — `CallableObject::call()` filters the
 * map down to the declared parameter names before forwarding.
 *
 * Decorator-style usage (aiogram's `@observer()` form) is *not* mirrored: PHP
 * lacks Python-style decorators, so callers use the imperative
 * `register($callback)` form. The method returns the callback unchanged to
 * preserve the assignment ergonomics:
 *
 *     $callback = $observer->register($callback);
 */
final class EventObserver
{
  /** @var list<CallableObject> */
  private array $handlers = [];

  /**
   * Register a handler. Returns the **original** callback unchanged so the
   * registration call site can keep a reference to the same closure (parity
   * with upstream `register()` which appends to `self.handlers` and returns
   * the callback in the decorator path). Internally the closure is wrapped in
   * a `CallableObject` for cached reflection-based kwarg binding.
   */
  public function register(Closure $callback): Closure
  {
    $this->handlers[] = new CallableObject($callback);

    return $callback;
  }

  /**
   * Synchronous fan-out. Each handler is invoked in registration order with
   * `$args` forwarded positionally and `$kwargs` filtered down to the
   * parameter names that handler actually declares. No return values are
   * consumed: lifecycle observers are pub/sub. If a handler throws, the
   * exception propagates and later handlers do **not** run (mirrors
   * upstream's lack of try/except in `EventObserver.trigger`).
   *
   * @param array<int, mixed> $args
   * @param array<string, mixed> $kwargs
   */
  public function trigger(array $args = [], array $kwargs = []): void
  {
    foreach ($this->handlers as $handler) {
      $handler->call($args, $kwargs);
    }
  }

  /**
   * Drop all registered handlers. After `clear()`, `trigger()` is a no-op
   * until new handlers are registered.
   */
  public function clear(): void
  {
    $this->handlers = [];
  }

  /**
   * Expose the registered handlers (wrapped as `CallableObject` instances)
   * for inspection by tests and introspection code. Production dispatch
   * always goes through `trigger()`.
   *
   * @return list<CallableObject>
   */
  public function handlers(): array
  {
    return $this->handlers;
  }
}
