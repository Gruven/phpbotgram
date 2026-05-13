<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher\Event;

use Closure;
use Gruven\PhpBotGram\Dispatcher\Flags\Flags;
use Gruven\PhpBotGram\Dispatcher\Middlewares\BaseMiddleware;
use Gruven\PhpBotGram\Dispatcher\Middlewares\MiddlewareManager;
use Gruven\PhpBotGram\Dispatcher\Router;

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
 * **Middleware integration** — the observer owns two `MiddlewareManager`
 * collections that compose around the dispatch primitive:
 *
 * - `$outerMiddleware` wraps the **entire observer** (global filter chain
 *   plus the handler iteration). This is where `UserContextMiddleware` /
 *   `ErrorsMiddleware` land — they need to inject context / catch
 *   exceptions across all handlers, including the global-filter rejection
 *   short-circuit.
 * - `$innerMiddleware` wraps **each individual handler invocation**
 *   (`HandlerObject::call`). This is where per-handler concerns like
 *   throttling, auth gates, and cache hits attach.
 *
 * The split mirrors upstream's `outer_middlewares` / `middlewares` lists on
 * `TelegramEventObserver` at `aiogram/dispatcher/event/telegram.py:23-31`.
 * The two managers are independent — registering on one does NOT register
 * on the other — and the outer chain has access to the kwargs the global
 * filters would see, while the inner chain runs only after the per-handler
 * filter check accepts.
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
   * Outer middleware chain — wraps the entire observer (global filters +
   * handler iteration). Registered via `outerMiddleware()`. The Dispatcher
   * attaches `UserContextMiddleware` and `ErrorsMiddleware` here at
   * construction time so error catch / context injection cover every
   * dispatch path including rejection short-circuits.
   */
  public readonly MiddlewareManager $outerMiddleware;

  /**
   * Inner middleware chain — wraps each individual handler invocation.
   * Registered via `innerMiddleware()`. Per-handler concerns (throttling,
   * cache lookup, auth gates) attach here so the rest of the observer
   * (global filter chain, per-handler filter pipeline) runs first.
   */
  public readonly MiddlewareManager $innerMiddleware;

  /**
   * Back-reference to the owning `Router`. `null` when the observer is
   * constructed standalone (legitimate for unit tests that don't need
   * chain-head inheritance). Populated by `Router::__construct` for every
   * observer in the schema-derived map so `resolveMiddlewares()` can
   * walk the ancestor chain via `Router::$parentRouter`.
   *
   * Mirrors upstream's `TelegramEventObserver(router=self, event_name=...)`
   * constructor (`telegram.py:26-28`). The reference is `?Router` not
   * `Router` because the observer is sometimes built before its router
   * (PHP property initializers run before the `__construct` body, but
   * the observer's own ctor needs the router reference up-front), and
   * `null` is the contract-safe default for unit-test instantiation.
   */
  public readonly ?Router $router;

  /**
   * @param string $eventName Wire-level Bot API key this observer routes
   *                          for (`message`, `callback_query`, …). The dispatcher uses this
   *                          when injecting the `event_name` kwarg into middleware data.
   * @param ?Router $router Back-reference to the owning router. Populated
   *                        by `Router::__construct`; left `null` for tests
   *                        that drive the observer in isolation.
   */
  public function __construct(public readonly string $eventName, ?Router $router = null)
  {
    $this->outerMiddleware = new MiddlewareManager();
    $this->innerMiddleware = new MiddlewareManager();
    $this->router = $router;
  }

  /**
   * Resolve every inner-middleware instance that should wrap each
   * handler invocation on this observer — self's own plus every ancestor
   * router's matching observer's inner middleware. Mirrors upstream's
   * `_resolve_middlewares` (`telegram.py:49-56`):
   *
   *     for router in reversed(tuple(self.router.chain_head)):
   *         observer = router.observers.get(self.event_name)
   *         if observer:
   *             middlewares.extend(observer.middleware)
   *     return middlewares
   *
   * `chain_head` walks self → parent → root; `reversed` then yields
   * root → … → self, so the outermost link of the composed chain is the
   * root router's middleware. The PHP port iterates the same order: we
   * walk from `$this->router` upward, push each ancestor's matching
   * observer's middleware onto an in-progress list, then return that
   * list reversed.
   *
   * Returns an empty list when `$this->router` is null (standalone
   * observers used in unit tests carry their own inner middleware via
   * `$this->innerMiddleware` only).
   *
   * @return list<BaseMiddleware> Composed innermost-to-outermost; callers
   *                              pass to `MiddlewareManager::wrap` semantics
   *                              (first element wraps outermost).
   */
  public function resolveMiddlewares(): array
  {
    if ($this->router === null) {
      // Standalone observer (no router back-reference): only its own
      // inner chain applies. We still return the inner-middleware list
      // verbatim so triggerCore's single composition path doesn't have
      // to special-case the standalone case.
      $own = [];

      foreach ($this->innerMiddleware as $mw) {
        $own[] = $mw;
      }

      return $own;
    }

    // Walk self → parent → root, pushing each ancestor's observer's
    // inner middleware onto a flat list, then reverse so the root's
    // middleware ends up outermost. Matches upstream's `reversed(chain_head)`
    // walk shape.
    $reverseChain = [];
    $router = $this->router;

    while ($router !== null) {
      $observer = $router->observers[$this->eventName] ?? null;

      if ($observer !== null) {
        foreach ($observer->innerMiddleware as $mw) {
          $reverseChain[] = $mw;
        }
      }

      $router = $router->parentRouter;
    }

    return array_reverse($reverseChain);
  }

  /**
   * Append a middleware to the **outer** chain (wraps the whole observer).
   * Thin wrapper around `$outerMiddleware->register()` that exists so
   * Dispatcher's setup code reads `$observer->outerMiddleware(new …)` —
   * matching upstream's `observer.outer_middleware(...)` call site.
   */
  public function outerMiddleware(BaseMiddleware $middleware): BaseMiddleware
  {
    return $this->outerMiddleware->register($middleware);
  }

  /**
   * Append a middleware to the **inner** chain (wraps each handler call).
   * Mirror of `outerMiddleware()` for the per-handler chain.
   */
  public function innerMiddleware(BaseMiddleware $middleware): BaseMiddleware
  {
    return $this->innerMiddleware->register($middleware);
  }

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
   * Route an event through the outer-middleware chain, then through global
   * filters and the handler chain (the dispatch primitive lives in
   * `triggerCore()`).
   *
   * `$event` is typed `object` because dispatcher-synthetic events (notably
   * `ErrorEvent`, which deliberately does not extend `TelegramObject`) flow
   * through the same dispatch primitive. Handler-declared parameter types
   * are checked by `CallableObject`'s reflection adapter when binding the
   * `event` kwarg.
   *
   * @param array<string, mixed> $kwargs Dispatcher context (bot,
   *                                     event_context, state, …) merged with filter-result injections
   *                                     before reaching each handler.
   *
   * @return mixed The first claiming handler's return value, or
   *               `UnhandledSentinel::instance()` if every handler passed, or
   *               `RejectedSentinel::instance()` if a global filter rejected.
   */
  public function trigger(object $event, array $kwargs = []): mixed
  {
    $terminal = fn(object $e, array $k): mixed => $this->triggerCore($e, $k);
    $chain = $this->outerMiddleware->wrap($terminal);

    return $chain($event, $kwargs);
  }

  /**
   * The actual dispatch primitive (post-outer-middleware): runs global
   * filters then iterates handlers with per-handler filter check and
   * inner-middleware wrapping.
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
   *    c. Success: invoke the handler via the inner-middleware chain.
   *       If the result is `UnhandledSentinel` the handler explicitly opts
   *       out and we `continue`; otherwise its return value is the trigger
   *       result.
   * 3. If no handler claimed the event, return `UnhandledSentinel`.
   *
   * The `event` kwarg is injected before filters run so filter and handler
   * signatures can declare the event as a named parameter. Deviation from
   * upstream's `handler.call(event, **kwargs)`: we pass `event` **only** as
   * a kwarg, never positional. The PHP kwarg-binding model uses parameter
   * names, and forwarding `event` positionally AND as a kwarg collides
   * with PHP 8.1+ "Named parameter overwrites previous argument" guard.
   *
   * Sentinel return values are intentionally distinct objects (compared by
   * identity via `===`) so a handler that legitimately returns `null` is
   * not confused with "no handler ran".
   *
   * @param array<string, mixed> $kwargs
   */
  private function triggerCore(object $event, array $kwargs): mixed
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

    // Resolve the inner-middleware chain ONCE for this trigger: self's
    // own middleware plus every ancestor router's matching observer's
    // middleware. Mirrors upstream `_resolve_middlewares` walking
    // `chain_head` (`telegram.py:49-56` + the wrap call at
    // `telegram.py:122-126`).
    //
    // For standalone observers (no router back-reference, e.g. unit
    // tests), `resolveMiddlewares` returns this observer's own
    // `$innerMiddleware` flat — no inheritance to perform, but the
    // composition path stays uniform.
    $resolvedMiddlewares = $this->resolveMiddlewares();

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

      // Inner middleware wraps the handler's `call()`. The terminal step
      // ignores the closure's `$event` (positional) argument and forwards
      // only kwargs — the kwarg merge above already carries `event` so
      // forwarding it positionally would collide with PHP's "named
      // parameter overwrites previous argument" guard.
      $handlerTerminal = static fn(object $e, array $k): mixed => $handler->call([], $k);

      // Compose the resolved chain around the terminal. `array_reverse`
      // so the first element of `$resolvedMiddlewares` (root-most)
      // wraps the outermost layer. Identical semantics to
      // `MiddlewareManager::wrap` but free of the WeakMap cache (the
      // terminal closure is fresh per handler so the cache never hits).
      $wrappedHandler = $handlerTerminal;

      foreach (array_reverse($resolvedMiddlewares) as $middleware) {
        $next = $wrappedHandler;
        $wrappedHandler = static fn(object $e, array $k): mixed => $middleware($next, $e, $k);
      }
      $response = $wrappedHandler($event, $handlerKwargs);

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
