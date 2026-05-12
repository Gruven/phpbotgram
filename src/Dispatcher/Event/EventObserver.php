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
 * Phase-3 NOTE: handlers are stored as raw `\Closure` instances. Task 3.2 will
 * wrap them in `CallableObject` (reflection-cached kwargs binding) — the public
 * surface (`register/trigger/clear/handlers`) is kept stable so the refactor is
 * internal.
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
  /** @var list<Closure> */
  private array $handlers = [];

  /**
   * Register a handler. Returns the callback unchanged so the registration
   * call site can keep a reference to the same closure (parity with upstream
   * `register()` which appends to `self.handlers` and returns the callback in
   * the decorator path).
   */
  public function register(Closure $callback): Closure
  {
    $this->handlers[] = $callback;

    return $callback;
  }

  /**
   * Synchronous fan-out. Each handler is invoked in registration order with
   * the supplied arguments — positional **and** named via PHP variadic
   * argument unpacking. No return values are consumed: lifecycle observers
   * are pub/sub. If a handler throws, the exception propagates and later
   * handlers do **not** run (mirrors upstream's lack of try/except in
   * `EventObserver.trigger`).
   */
  public function trigger(mixed ...$args): void
  {
    foreach ($this->handlers as $handler) {
      $handler(...$args);
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
   * Expose the registered handlers for inspection by tests and introspection
   * code. Production dispatch always goes through `trigger()`.
   *
   * @return list<Closure>
   */
  public function handlers(): array
  {
    return $this->handlers;
  }
}
