<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher\Event;

use Closure;
use Gruven\PhpBotGram\Dispatcher\Flags\Flags;
use Gruven\PhpBotGram\Types\TelegramObject;

/**
 * Routing observer for a single Telegram update type — port of
 * `aiogram.dispatcher.event.telegram.TelegramEventObserver`.
 *
 * Each `Router` owns one of these per Bot API event key (`message`,
 * `callback_query`, `inline_query`, …). It tracks two collections:
 *
 * - **Handlers** — registered callbacks, each wrapped in a `HandlerObject`
 *   that carries its filter pipeline and flags. Dispatched in registration
 *   order; the **first** handler whose filters all accept claims the event
 *   and its return value is the trigger's result.
 * - **Global filters** — predicates applied **before** any handler dispatch.
 *   A single rejection (`false` / `null` / `[]`) short-circuits the entire
 *   chain to `RejectedSentinel` — no handlers are tried. Used by scenes to
 *   scope every handler on the observer to the current scene state.
 *
 * The `register()` signature is a deliberate deviation from upstream's
 * Python form `register(callback, *filters, flags=None)`. PHP cannot place
 * a named optional parameter after a variadic, and we want the friendly
 * call-site `register($cb, [$f1, $f2], ['admin_only' => true])` to work
 * without quirky `null`-padding. Filters become a `list<Closure>` and
 * flags become an `array<string, mixed>`; both are optional and positional.
 *
 * `__invoke()` keeps the decorator-factory ergonomics: passed a callback
 * it registers eagerly, otherwise it returns a registration closure. PHP
 * lacks Python decorator syntax, but this two-shape `__invoke` lets us
 * support both `$obs($cb, flags: [...])` and the factory form
 * `$register = $obs(filters: [...]); $register($cb);`.
 *
 * The flags-merge semantics match upstream `flags = {**extract_flags_from_object(cb), **flags}`:
 * attribute / WeakMap-attached flags are merged in first, then the manual
 * `$flags` argument overlays them last (manual wins on key collision).
 *
 * Middleware chains land in Task 3.10 (Dispatcher wiring) — this observer
 * is the dispatch primitive they wrap around.
 */
final class TelegramEventObserver
{
  /**
   * Registered handlers in declaration order. Exposed read-only so the
   * dispatcher / introspection code can iterate; mutation goes through
   * `register()` / `clear()`.
   *
   * @var list<HandlerObject>
   */
  public private(set) array $handlers = [];

  /**
   * Global filters applied before any handler dispatch — every filter must
   * accept (truthy result, i.e. `!$result === false`) for the handler chain
   * to be considered. Append via `filter()`.
   *
   * @var list<FilterObject>
   */
  public private(set) array $filters = [];

  /**
   * @param string $eventName Wire-level Bot API key this observer routes
   *                          for (`message`, `callback_query`, …). The dispatcher uses this
   *                          when injecting the `event_name` kwarg into middleware data.
   */
  public function __construct(public readonly string $eventName) {}

  /**
   * Append global filters that gate every handler on this observer.
   *
   * Each closure is wrapped in a `FilterObject` (reflection-cached kwarg
   * binding). Multiple `filter()` calls accumulate — the filters run in
   * insertion order during `trigger()`, and the first one to reject (any
   * falsy return) short-circuits the dispatch to `RejectedSentinel`.
   */
  public function filter(Closure ...$filters): void
  {
    foreach ($filters as $f) {
      $this->filters[] = new FilterObject($f);
    }
  }

  /**
   * Register a handler with optional per-handler filters and flag metadata.
   *
   * The Python upstream uses `register(callback, *filters, flags=None)`;
   * PHP cannot place named-optional parameters after a variadic, so we
   * flatten filters into a `list<Closure>` and flags into a positional
   * associative array. Both default to empty.
   *
   * Flag-merge order matches upstream:
   * 1. Read `#[Flag]` attributes and imperative `FlagDecorator::attach()`
   *    attachments from the callback (`Flags::extractFlags`).
   * 2. Overlay the manual `$flags` argument on top — **manual wins** on
   *    key collision (`{**attribute, **manual}` semantics).
   *
   * Returns the original callback unchanged so registration sites can keep
   * a reference for re-use (matches `EventObserver::register()` ergonomics).
   *
   * @param list<Closure> $filters Per-handler filter pipeline. An
   *                               empty list means "always accept" — fall-through to the handler itself.
   * @param array<string, mixed> $flags Manual flag overrides. Merged with
   *                                    attribute / WeakMap-attached flags via `Flags::extractFlags`.
   */
  public function register(
    Closure $callback,
    array $filters = [],
    array $flags = [],
  ): Closure {
    $filterObjects = [];

    foreach ($filters as $f) {
      $filterObjects[] = new FilterObject($f);
    }

    // Read attribute / WeakMap flags off the callback first, then overlay
    // the manual map. `[...$a, ...$b]` is the PHP equivalent of Python's
    // `{**a, **b}` — string keys from `$b` overwrite duplicates in `$a`,
    // giving us the "manual wins" semantics upstream documents.
    $attributeFlags = [];

    foreach (Flags::extractFlags($callback) as $flag) {
      $attributeFlags[$flag->name] = $flag->value;
    }

    $mergedFlags = [...$attributeFlags, ...$flags];

    $this->handlers[] = new HandlerObject($callback, $filterObjects, $mergedFlags);

    return $callback;
  }

  /**
   * Dual-shape decorator entrypoint mirroring upstream's `__call__`.
   *
   * - Eager form: `$observer($cb, filters: [...], flags: [...])` registers
   *   immediately and returns the original callback.
   * - Factory form: `$register = $observer(filters: [...], flags: [...]);`
   *   returns a registration closure to be invoked later with the callback.
   *
   * The factory form is the PHP idiom for the Python decorator
   * `@router.message(F::text->equals('hi'), flags=[...])` — we can't apply
   * a decorator with the `@` syntax, but we can produce the same closure
   * factory.
   *
   * @param list<Closure> $filters
   * @param array<string, mixed> $flags
   *
   * @return Closure(Closure): Closure In factory mode, a closure that
   *                                   accepts the callback and registers it. In eager mode, the
   *                                   original callback (already registered).
   */
  public function __invoke(
    ?Closure $callback = null,
    array $filters = [],
    array $flags = [],
  ): Closure {
    if ($callback !== null) {
      return $this->register($callback, $filters, $flags);
    }

    return fn(Closure $cb): Closure => $this->register($cb, $filters, $flags);
  }

  /**
   * Route an event through global filters then the handler chain.
   *
   * Sequence — matches upstream `TelegramEventObserver.trigger`:
   *
   * 1. Iterate global `$filters`. Each runs against `$event` + `$kwargs`.
   *    A falsy result aborts dispatch with `RejectedSentinel`; an array
   *    result is merged into `$kwargs` for downstream filter and handler
   *    consumption.
   * 2. Iterate `$handlers` in registration order. For each:
   *    a. Inject `'handler' => $handler` into kwargs (the dispatcher
   *       contract — handlers can declare `HandlerObject $handler`).
   *    b. Run the handler's filter pipeline via `HandlerObject::check`.
   *       Failure: `continue` to the next handler.
   *    c. Success: invoke the handler. If it returns `UnhandledSentinel`
   *       it explicitly opts out and we `continue`; otherwise its return
   *       value is the trigger result.
   * 3. If no handler claimed the event, return `UnhandledSentinel`.
   *
   * The `event` kwarg is injected before filters run so filter and handler
   * signatures can declare `TelegramObject $event` and receive the payload
   * by named parameter. Deviation from upstream's `handler.call(event, **kwargs)`:
   * we pass `event` **only** as a kwarg, never positional. The PHP kwarg-
   * binding model uses parameter names, and forwarding `event` positionally
   * AND as a kwarg collides with PHP 8.1+ "Named parameter overwrites
   * previous argument" guard. Handlers naturally declare
   * `function (TelegramObject $event, ...)` and the reflection adapter
   * binds the named kwarg.
   *
   * Sentinel return values are intentionally distinct objects (compared by
   * identity via `===`) so a handler that legitimately returns `null` is
   * not confused with "no handler ran".
   *
   * @param array<string, mixed> $kwargs Dispatcher context (bot,
   *                                     event_context, state, …) merged with filter-result injections
   *                                     before reaching each handler.
   *
   * @return mixed The first claiming handler's return value, or
   *               `UnhandledSentinel::instance()` if every handler passed, or
   *               `RejectedSentinel::instance()` if a global filter rejected.
   */
  public function trigger(TelegramObject $event, array $kwargs = []): mixed
  {
    foreach ($this->filters as $globalFilter) {
      $result = $globalFilter->call([], ['event' => $event, ...$kwargs]);

      if (!$result) {
        // Falsy = reject. Stops the chain immediately; no handlers run.
        return RejectedSentinel::instance();
      }

      if (is_array($result)) {
        // Truthy array = accept + merge. Subsequent filters AND handlers
        // see the injected kwargs. Matches Python `kwargs.update(result)`.
        $kwargs = [...$kwargs, ...$result];
      }
      // Otherwise (true / int / non-empty string / object): accept with no
      // merge. Drop through to the next filter.
    }

    foreach ($this->handlers as $handler) {
      $kwargsWithHandler = [...$kwargs, 'handler' => $handler];

      [$check, $handlerKwargs] = $handler->check(
        [],
        ['event' => $event, ...$kwargsWithHandler],
      );

      if (!$check) {
        // Filter pipeline rejected; try the next handler. The kwargs
        // accumulated by accepted filters before the rejection are
        // discarded — they live only in this handler's check scope.
        continue;
      }

      // Pass `event` only as a kwarg — `$handlerKwargs` already carries it
      // via the merge above. Forwarding it positionally too would trigger
      // PHP 8.1+'s "Named parameter overwrites previous argument" error
      // when the handler declares `TelegramObject $event` as a named param.
      $response = $handler->call([], $handlerKwargs);

      if ($response === UnhandledSentinel::instance()) {
        // Handler ran but voluntarily passed. Continue to the next.
        continue;
      }

      return $response;
    }

    return UnhandledSentinel::instance();
  }

  /**
   * Drop every registered handler and global filter. Intended for test
   * isolation and router rebuilds — production routing never calls this.
   */
  public function clear(): void
  {
    $this->handlers = [];
    $this->filters = [];
  }
}
