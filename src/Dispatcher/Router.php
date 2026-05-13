<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher;

use Gruven\PhpBotGram\Dispatcher\Event\EventObserver;
use Gruven\PhpBotGram\Dispatcher\Event\RejectedSentinel;
use Gruven\PhpBotGram\Dispatcher\Event\TelegramEventObserver;
use Gruven\PhpBotGram\Dispatcher\Event\UnhandledSentinel;
use LogicException;

/**
 * Per-update-type router — port of `aiogram.dispatcher.router.Router`.
 *
 * A `Router` owns one `TelegramEventObserver` for **every** Telegram
 * update type (25 today: `message`, `edited_message`, …, `managed_bot`)
 * plus a separate `errors` observer for the error-propagation channel,
 * and two `EventObserver` instances (`startup` / `shutdown`) for
 * lifecycle hooks.
 *
 * Routers compose into a tree via `includeRouter()`; `propagateEvent()`
 * dispatches an update to the local observer, and on `UnhandledSentinel`
 * fall-through, walks `subRouters` depth-first. Cycles, self-attachment,
 * and re-parenting are rejected at attach time with `LogicException`.
 *
 * **Why `UPDATE_TYPES` is a hand-maintained constant, not generated**:
 * the list is derived from `Types/Update.php` (Phase 2 codegen output)
 * but Router does not import Update — that would force Router to know
 * the full type graph just to dispatch by string key. The constant is
 * tested against the schema-driven list in `RouterTest`, so a Phase 2
 * regeneration that adds/removes an update field surfaces here as a
 * test failure.
 *
 * **camelCase property aliases** (`$router->editedMessage` etc.) are
 * initialized as direct references to the same `TelegramEventObserver`
 * instances stored in `$observers['edited_message']`. Spec § "Event
 * name conventions" mandates this dual API: snake_case wire names for
 * iteration, camelCase for ergonomic registration call sites.
 *
 * `Router` is **not** declared `final` — `Dispatcher` (Task 3.10)
 * extends it to add polling/webhook entry points.
 */
class Router
{
  /**
   * Wire-level Bot API update keys this router routes for.
   *
   * Derived from regenerated `src/Types/Update.php`: each non-`updateId`,
   * non-`bot` constructor parameter on `Update` becomes a key here, with
   * camelCase converted to snake_case to match the wire payload. The
   * order mirrors `Update`'s parameter order so iterations are
   * deterministic across PHP versions.
   *
   * Sync invariant: whenever Phase 2 regen changes `Update.php`, this
   * list must be updated. `RouterTest::testUpdateTypesConstantMatchesUpdateSchema`
   * is the canary.
   *
   * **`error` is NOT in this list** — it's a separate channel for the
   * `ErrorsMiddleware` and lives in `$observers['error']` /
   * `$this->errors`. Update propagation never targets it; the only way
   * to reach it is via `propagateEvent('error', ...)` from the error
   * middleware.
   *
   * @var list<string>
   */
  public const array UPDATE_TYPES = [
    'message',
    'edited_message',
    'channel_post',
    'edited_channel_post',
    'business_connection',
    'business_message',
    'edited_business_message',
    'deleted_business_messages',
    'guest_message',
    'message_reaction',
    'message_reaction_count',
    'inline_query',
    'chosen_inline_result',
    'callback_query',
    'shipping_query',
    'pre_checkout_query',
    'purchased_paid_media',
    'poll',
    'poll_answer',
    'my_chat_member',
    'chat_member',
    'chat_join_request',
    'chat_boost',
    'removed_chat_boost',
    'managed_bot',
  ];

  /**
   * Update types upstream excludes from `resolve_used_update_types`
   * regardless of registered handlers. Upstream uses a frozenset literal;
   * the port keeps it as a `list<string>` for `in_array` lookups.
   * `'update'` mirrors upstream's INTERNAL_UPDATE_TYPES but is never an
   * observer key on Router — included for forward-compat with upstream.
   *
   * @var list<string>
   */
  private const array INTERNAL_UPDATE_TYPES = ['update', 'error'];

  /**
   * Debug-only identifier. Defaults to `spl_object_hash($this)` (PHP
   * equivalent of upstream's `hex(id(self))`) when no explicit name is
   * given. The hash is stable for the object's lifetime but **not**
   * guaranteed unique across two unrelated instances of the same process
   * after one has been garbage-collected — that's a Python parity quirk,
   * not a bug.
   */
  public readonly string $name;

  /**
   * Lifecycle hook fan-out for polling/webhook startup. Handlers are
   * registered via `$router->startup->register($cb)` and fire in
   * registration order on `emitStartup()`. Receives the workflow_data
   * kwarg bag merged with `router: $this`.
   */
  public readonly EventObserver $startup;

  /**
   * Mirror of `$startup` for graceful shutdown.
   */
  public readonly EventObserver $shutdown;

  /**
   * Wire-name keyed map of every Telegram observer this router owns
   * (one per `UPDATE_TYPES` entry plus `'error'`). External iteration
   * goes through this map; per-type ergonomics use the camelCase
   * properties below, which are direct references to the same instances.
   *
   * @var array<string, TelegramEventObserver>
   */
  public private(set) array $observers = [];

  /**
   * Parent in the router composition tree, set by `includeRouter()`.
   * `null` until attached; immutable thereafter (re-parenting throws).
   *
   * The set is **on the child**, not the parent: `$parent->includeRouter($child)`
   * writes `$child->parentRouter = $parent`. Mirrors upstream's
   * `parent_router` property setter semantics at `router.py:217-246`.
   */
  public private(set) ?Router $parentRouter = null;

  /**
   * Children attached via `includeRouter()`, in registration order.
   * Used by `propagateEvent` for depth-first fall-through and by
   * `emitStartup`/`emitShutdown` for tree traversal.
   *
   * @var list<Router>
   */
  public private(set) array $subRouters = [];

  // Per-update-type aliases (camelCase). Each is a direct reference to
  // the same instance in `$observers['<snake_case>']` — initialized in
  // the constructor and frozen as `readonly` so the dual-API invariant
  // can't be broken by external mutation. Order mirrors UPDATE_TYPES.
  public readonly TelegramEventObserver $message;
  public readonly TelegramEventObserver $editedMessage;
  public readonly TelegramEventObserver $channelPost;
  public readonly TelegramEventObserver $editedChannelPost;
  public readonly TelegramEventObserver $businessConnection;
  public readonly TelegramEventObserver $businessMessage;
  public readonly TelegramEventObserver $editedBusinessMessage;
  public readonly TelegramEventObserver $deletedBusinessMessages;
  public readonly TelegramEventObserver $guestMessage;
  public readonly TelegramEventObserver $messageReaction;
  public readonly TelegramEventObserver $messageReactionCount;
  public readonly TelegramEventObserver $inlineQuery;
  public readonly TelegramEventObserver $chosenInlineResult;
  public readonly TelegramEventObserver $callbackQuery;
  public readonly TelegramEventObserver $shippingQuery;
  public readonly TelegramEventObserver $preCheckoutQuery;
  public readonly TelegramEventObserver $purchasedPaidMedia;
  public readonly TelegramEventObserver $poll;
  public readonly TelegramEventObserver $pollAnswer;
  public readonly TelegramEventObserver $myChatMember;
  public readonly TelegramEventObserver $chatMember;
  public readonly TelegramEventObserver $chatJoinRequest;
  public readonly TelegramEventObserver $chatBoost;
  public readonly TelegramEventObserver $removedChatBoost;
  public readonly TelegramEventObserver $managedBot;

  /**
   * Errors-channel observer — read via `$router->errors` (matches
   * upstream's `self.errors = self.error = TelegramEventObserver(...)`).
   * Not part of UPDATE_TYPES because there is no `error` wire payload;
   * `ErrorsMiddleware` synthesizes the event and invokes
   * `propagateEvent('error', ...)`.
   */
  public readonly TelegramEventObserver $errors;

  public function __construct(?string $name = null)
  {
    // `spl_object_hash` is PHP's closest analogue to Python's `id()` —
    // a 32-char hex string unique for the object's lifetime.
    $this->name = $name ?? spl_object_hash($this);
    $this->startup = new EventObserver();
    $this->shutdown = new EventObserver();

    // Build observers map keyed by wire name; for each known update type
    // we also stash a readonly camelCase alias property that points at
    // the same instance, so `$router->editedMessage` and
    // `$router->observers['edited_message']` are guaranteed identical.
    //
    // Each observer carries a back-reference to `$this` so its
    // `resolveMiddlewares()` can walk the ancestor chain via
    // `$router->parentRouter` at trigger time (Fix C2 — chain_head
    // middleware inheritance). Standalone observers built without a
    // router argument fall back to their own inner middleware only.
    foreach ([...self::UPDATE_TYPES, 'error'] as $eventName) {
      $this->observers[$eventName] = new TelegramEventObserver($eventName, $this);
    }

    $this->message = $this->observers['message'];
    $this->editedMessage = $this->observers['edited_message'];
    $this->channelPost = $this->observers['channel_post'];
    $this->editedChannelPost = $this->observers['edited_channel_post'];
    $this->businessConnection = $this->observers['business_connection'];
    $this->businessMessage = $this->observers['business_message'];
    $this->editedBusinessMessage = $this->observers['edited_business_message'];
    $this->deletedBusinessMessages = $this->observers['deleted_business_messages'];
    $this->guestMessage = $this->observers['guest_message'];
    $this->messageReaction = $this->observers['message_reaction'];
    $this->messageReactionCount = $this->observers['message_reaction_count'];
    $this->inlineQuery = $this->observers['inline_query'];
    $this->chosenInlineResult = $this->observers['chosen_inline_result'];
    $this->callbackQuery = $this->observers['callback_query'];
    $this->shippingQuery = $this->observers['shipping_query'];
    $this->preCheckoutQuery = $this->observers['pre_checkout_query'];
    $this->purchasedPaidMedia = $this->observers['purchased_paid_media'];
    $this->poll = $this->observers['poll'];
    $this->pollAnswer = $this->observers['poll_answer'];
    $this->myChatMember = $this->observers['my_chat_member'];
    $this->chatMember = $this->observers['chat_member'];
    $this->chatJoinRequest = $this->observers['chat_join_request'];
    $this->chatBoost = $this->observers['chat_boost'];
    $this->removedChatBoost = $this->observers['removed_chat_boost'];
    $this->managedBot = $this->observers['managed_bot'];

    $this->errors = $this->observers['error'];
  }

  /**
   * Attach a child router. Validates the operation against three
   * mistakes that would corrupt the tree:
   *
   * 1. **Self-attachment** — a router cannot include itself. Upstream
   *    raises RuntimeError; we use LogicException because PHP doesn't
   *    have a Runtime/Logic distinction this fine, and "you wired
   *    your router tree wrong" is unambiguously a programming bug.
   * 2. **Re-parenting** — once a router has a parent it stays put.
   *    The alternative (detach + re-attach) would silently leave the
   *    old parent's `subRouters` array holding a dangling reference.
   * 3. **Cycles** — A→B→C→A would make `propagateEvent` infinitely
   *    recurse. We walk our own ancestor chain (`parentRouter`
   *    upward) and confirm the candidate isn't already in it.
   *
   * Returns the **included** router so callers can chain fluent
   * registrations: `$root->includeRouter($child)->message->register(...)`.
   * Matches upstream `include_router(...) -> Router`.
   */
  public function includeRouter(Router $router): Router
  {
    if ($router === $this) {
      throw new LogicException("Router '{$this->name}' cannot include itself");
    }

    if ($router->parentRouter !== null) {
      throw new LogicException(
        "Router '{$router->name}' is already attached to '{$router->parentRouter->name}'",
      );
    }

    // Cycle detection: $router would become our descendant, so it must
    // not already be one of our ancestors. Walk the parent chain
    // starting at `$this`. Matches upstream's `while parent:` loop.
    $ancestor = $this;

    while ($ancestor !== null) {
      if ($ancestor === $router) {
        throw new LogicException(
          "Cycle detected including router '{$router->name}' into '{$this->name}'",
        );
      }

      $ancestor = $ancestor->parentRouter;
    }

    $router->parentRouter = $this;
    $this->subRouters[] = $router;

    return $router;
  }

  /**
   * Variadic convenience for attaching several children at once.
   * Each is validated independently; the first failure throws and
   * already-attached siblings stay attached (matches upstream's
   * `for router in routers: self.include_router` semantics — no
   * transaction).
   *
   * Returns `$this` for fluent chaining at the parent (note: upstream's
   * `include_routers` returns `None`; the port returns the parent so
   * users can write `(new Dispatcher())->includeRouters(...)->runPolling(...)`).
   */
  public function includeRouters(Router ...$routers): static
  {
    foreach ($routers as $router) {
      $this->includeRouter($router);
    }

    return $this;
  }

  /**
   * Collect the snake_case names of every update type with at least
   * one registered handler anywhere in the tree rooted at `$this`.
   *
   * Used by the polling driver to compute `allowed_updates` for
   * `getUpdates` — Telegram only sends updates of types the bot
   * cares about, so this minimizes bandwidth. Matches upstream
   * `resolve_used_update_types` exactly:
   *
   * - **Excludes the `error` channel** (and the meta `update` type)
   *   regardless of handlers — they're internal, not wire types.
   * - **Honors `$skipEvents`** for caller-driven filtering on top of
   *   the internal exclusion.
   * - **Walks the full sub-router subtree** (depth-first), de-duped
   *   via associative-array keys (PHP's set substitute).
   *
   * Upstream returns a sorted list; the port returns the keys in
   * walk order so the result is deterministic per tree shape. Callers
   * that need sorting can `sort($result)` themselves.
   *
   * @param list<string> $skipEvents Additional update types to omit.
   *
   * @return list<string>
   */
  public function resolveUsedUpdateTypes(array $skipEvents = []): array
  {
    $skip = [...$skipEvents, ...self::INTERNAL_UPDATE_TYPES];
    $found = [];

    foreach ($this->observers as $name => $observer) {
      if (in_array($name, $skip, strict: true)) {
        continue;
      }

      if ($observer->handlers !== []) {
        $found[$name] = true;
      }
    }

    foreach ($this->subRouters as $child) {
      foreach ($child->resolveUsedUpdateTypes($skipEvents) as $type) {
        $found[$type] = true;
      }
    }

    return array_keys($found);
  }

  /**
   * Route an event through the local observer; on UNHANDLED fall
   * through to sub-routers in registration order.
   *
   * Contract:
   *
   * 1. Inject `event_router => $this` into the kwargs bag so handlers
   *    and middlewares can see *which router* is currently dispatching
   *    (`router.py:153`). The inner-most claiming router is the value
   *    handlers see — each recursion overwrites the kwarg.
   * 2. Look up the observer; throw `LogicException` on an unknown
   *    update type. Upstream silently returns `UNHANDLED` for unknown
   *    keys; the port is strict because our observer map is
   *    schema-derived and a missing key is unambiguously a bug
   *    (typo'd literal, stale code after a Phase 2 regen, …).
   * 3. Compose the local observer's outer middleware ONCE around an
   *    inner closure that runs the local observer's raw `trigger()`
   *    AND, on UNHANDLED, the depth-first sub-router walk. This is the
   *    Fix I2 shape — the parent observer's outer middleware covers
   *    sub-router handlers too. Mirrors upstream
   *    `Router.propagate_event` (`router.py:152-166`) which wraps
   *    `_wrapped` (containing the sub-router walk inside
   *    `_propagate_event`) with `observer.wrap_outer_middleware(...)`.
   * 4. Non-UNHANDLED return short-circuits and is returned verbatim —
   *    including `null`, `false`, `TelegramMethod` instances, etc.
   *
   * **Middleware integration**: outer middleware on a router observer
   * wraps the entire local dispatch *plus* the sub-router walk. The
   * Dispatcher subclass wires `UserContextMiddleware` /
   * `ErrorsMiddleware` at the `feedUpdate` ingress layer (above
   * `propagateEvent`), so those middlewares run once per ingress
   * regardless of where the claiming handler lives. Per-observer outer
   * middleware registered via `$observer->outerMiddleware(...)` runs
   * once per `propagateEvent` call on the owning router (so a parent's
   * outer middleware wraps a child router's claiming handler too).
   *
   * `$event` is typed `object` (not `TelegramObject`) because the same
   * propagation primitive carries synthetic dispatcher events such as
   * `ErrorEvent`, which deliberately do not extend `TelegramObject`.
   *
   * @param array<string, mixed> $kwargs Dispatcher context bag (bot,
   *                                     event_context, …) merged into the handler invocation.
   */
  public function propagateEvent(
    string $updateType,
    object $event,
    array $kwargs = [],
  ): mixed {
    if (!isset($this->observers[$updateType])) {
      throw new LogicException("Unknown update type: {$updateType}");
    }

    // Overwrite (don't merge): the inner-most router is the contextually
    // relevant one. Spread-then-assign lets a caller-supplied
    // event_router be overridden — matches upstream's `kwargs.update(...)`.
    $kwargs = [...$kwargs, 'event_router' => $this];

    $observer = $this->observers[$updateType];
    $subRouters = $this->subRouters;

    // Build the "inner" callable: local observer dispatch first, sub-
    // router fall-through second. Mirrors upstream `_propagate_event`'s
    // body (`router.py:168-197`) — the raw `trigger()` runs without
    // outer-middleware wrap, and the sub-router walk happens inside the
    // same closure so the OUTER wrap covers it.
    $inner = static function (object $e, array $data) use ($observer, $subRouters, $updateType): mixed {
      $response = $observer->trigger($e, $data);

      // Fix I6: collapse `RejectedSentinel` to `UnhandledSentinel` at the
      // Router boundary so external callers don't need to know about the
      // internal REJECTED sentinel. A handler that raised `CancelHandler`
      // (or a global filter that rejected) surfaces here as REJECTED;
      // the router treats that as "no observer in this tree claimed the
      // event" for fall-through purposes.
      if ($response === RejectedSentinel::instance()) {
        $response = UnhandledSentinel::instance();
      }

      if ($response !== UnhandledSentinel::instance()) {
        return $response;
      }

      foreach ($subRouters as $child) {
        $r = $child->propagateEvent($updateType, $e, $data);

        // Same collapse for the sub-router recursion: a child's REJECTED
        // surfaces here as UNHANDLED so we keep walking siblings instead
        // of short-circuiting on an internal sentinel the outer caller
        // doesn't recognise. (Note: `propagateEvent` already collapses
        // its own observer's REJECTED before returning, so a child can
        // only return UNHANDLED or a concrete value here — but keep the
        // guard for defence-in-depth against future refactors.)
        if ($r === RejectedSentinel::instance()) {
          continue;
        }

        if ($r !== UnhandledSentinel::instance()) {
          return $r;
        }
      }

      return UnhandledSentinel::instance();
    };

    // Compose the local observer's outer middleware around the inner
    // dispatch. `MiddlewareManager::wrap` short-circuits to the terminal
    // when no middlewares are registered (zero-allocation fast path), so
    // the wrap is cheap on routers without per-observer outer middleware.
    $wrapped = $observer->outerMiddleware->wrap($inner);

    return $wrapped($event, $kwargs);
  }

  /**
   * Fire the startup lifecycle hook depth-first across the tree.
   *
   * Injects `router => $this` into the kwargs bag so handlers can
   * declare `Router $router` and receive the *emitting* router at
   * each level — not the root. Matches upstream `emit_startup`
   * (`router.py:282`).
   *
   * Forwarded kwargs include the workflow_data and the
   * `bots[array_key_last]` injection from the polling driver (spec
   * § "Polling loop"). Lifecycle handlers are pub/sub: every handler
   * runs; the first throw aborts the rest (matches `EventObserver`).
   *
   * @param array<string, mixed> $kwargs Workflow data + injected `bot`.
   */
  public function emitStartup(array $kwargs = []): void
  {
    $kwargs = [...$kwargs, 'router' => $this];
    $this->startup->trigger([], $kwargs);

    foreach ($this->subRouters as $child) {
      // Pass the original parent's kwargs minus the `router` key —
      // the child's recursion will write its own. The spread-then-
      // overwrite below in the child's call handles that automatically.
      $child->emitStartup($kwargs);
    }
  }

  /**
   * Symmetric counterpart of `emitStartup` for graceful teardown.
   * Same traversal order (depth-first, registration order) and same
   * `router => $this` injection. Matches upstream `emit_shutdown`
   * (`router.py:295`).
   *
   * @param array<string, mixed> $kwargs
   */
  public function emitShutdown(array $kwargs = []): void
  {
    $kwargs = [...$kwargs, 'router' => $this];
    $this->shutdown->trigger([], $kwargs);

    foreach ($this->subRouters as $child) {
      $child->emitShutdown($kwargs);
    }
  }
}
