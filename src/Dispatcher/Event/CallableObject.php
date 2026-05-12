<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher\Event;

use Closure;
use ReflectionFunction;

/**
 * Reflection-cached "kwarg binder" mirroring upstream
 * `aiogram.dispatcher.event.handler.CallableObject`. The dispatcher injects a
 * large bag of context arguments (`bot`, `event_context`, `event_from_user`,
 * `state`, …) and each handler declares only the subset it actually needs.
 * `call()` filters the kwargs map down to the parameter names the underlying
 * `\Closure` exposes before forwarding it.
 *
 * Departures from the Python original (intentional, all sync-runtime):
 *
 * - No `awaitable` flag — phpbotgram is synchronous from the caller's PoV;
 *   Fibers handle non-blocking I/O at the transport layer and are invisible
 *   here, so we always invoke the closure directly with no `asyncio.to_thread`
 *   equivalent.
 * - `params` is preserved as an ordered map (`array<string,true>`) instead of
 *   an unordered `set[str]`; this keeps `params()` deterministic for tests and
 *   gives us a free O(1) membership check via `array_intersect_key`.
 * - The PHP variadic parameter (`...$args`) maps onto Python's `**kwargs`
 *   semantics — when present we forward every kwarg without filtering. PHP
 *   8.1+ `func(...$assoc)` treats string keys as named arguments, so a
 *   variadic closure receives them as its variadic array.
 */
final class CallableObject
{
  /**
   * Parameter names the closure declares, in declaration order, mapped to
   * `true` so `array_intersect_key` can filter the kwargs in one call.
   *
   * @var array<string, true>
   */
  private array $params;

  private bool $varKw;

  public function __construct(public readonly Closure $callback)
  {
    $reflection = new ReflectionFunction($callback);
    $params = [];
    $varKw = false;

    foreach ($reflection->getParameters() as $parameter) {
      if ($parameter->isVariadic()) {
        // A `...$rest` tail consumes any remaining named/positional args.
        // Mirror upstream: don't add the variadic name to `params` — it has
        // no meaning as a kwarg key.
        $varKw = true;

        continue;
      }
      $params[$parameter->getName()] = true;
    }

    $this->params = $params;
    $this->varKw = $varKw;
  }

  /**
   * Filter `$kwargs` down to the keys the closure actually declares. When the
   * closure has a variadic tail, every kwarg passes through untouched.
   *
   * @param array<string, mixed> $kwargs
   *
   * @return array<string, mixed>
   */
  public function prepareKwargs(array $kwargs): array
  {
    if ($this->varKw) {
      return $kwargs;
    }

    return array_intersect_key($kwargs, $this->params);
  }

  /**
   * Invoke the underlying closure. Positional `$args` are forwarded as-is;
   * `$kwargs` is filtered via `prepareKwargs()` so a closure declaring only
   * `$a` won't choke on an extra `bot:` kwarg the dispatcher injected.
   *
   * Relies on PHP 8.1+ named-argument unpacking: `($cb)(...$args, ...$kwargs)`
   * forwards the integer-keyed entries positionally and the string-keyed
   * entries as named arguments.
   *
   * Exceptions raised by the callback propagate unchanged — no try/catch,
   * matching upstream.
   *
   * @param array<int, mixed> $args
   * @param array<string, mixed> $kwargs
   */
  public function call(array $args = [], array $kwargs = []): mixed
  {
    $filtered = $this->prepareKwargs($kwargs);

    return ($this->callback)(...$args, ...$filtered);
  }

  /**
   * Names of the declared (non-variadic) parameters in source order. The
   * variadic tail, if any, is reported separately via `isVariadic()`.
   *
   * @return list<string>
   */
  public function params(): array
  {
    return array_keys($this->params);
  }

  /**
   * Whether the callback declares a variadic tail (`...$rest`). When true,
   * `prepareKwargs()` becomes a no-op pass-through.
   */
  public function isVariadic(): bool
  {
    return $this->varKw;
  }
}
