<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Dispatcher;

use Closure;
use Gruven\PhpBotGram\Dispatcher\Event\EventObserver;
use Gruven\PhpBotGram\Dispatcher\Event\TelegramEventObserver;
use Gruven\PhpBotGram\Dispatcher\Event\UnhandledSentinel;
use Gruven\PhpBotGram\Dispatcher\Middlewares\BaseMiddleware;
use Gruven\PhpBotGram\Dispatcher\Router;
use Gruven\PhpBotGram\Types\Chat;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 */
final class RouterTest extends TestCase
{
  public function testConstructionAutoDerivesNameFromObjectHash(): void
  {
    // Default name path: when no explicit name is supplied the router's name
    // is its `spl_object_hash` — same idea as upstream's `hex(id(self))`. The
    // contract is "unique, non-empty, useful for debugging" — assert
    // non-empty + uniqueness across instances rather than format specifics.
    $a = new Router();
    $b = new Router();

    self::assertNotSame('', $a->name);
    self::assertNotSame($a->name, $b->name);
  }

  public function testExplicitNameIsHonored(): void
  {
    $router = new Router('my_router');

    self::assertSame('my_router', $router->name);
  }

  public function testStartupAndShutdownObserversArePresent(): void
  {
    // Lifecycle hooks are `EventObserver` (not Telegram observers) — they
    // fan out positional/named args, no UNHANDLED semantics.
    $router = new Router();

    self::assertInstanceOf(EventObserver::class, $router->startup);
    self::assertInstanceOf(EventObserver::class, $router->shutdown);
  }

  public function testObserversMapHasEntryForEachUpdateTypePlusError(): void
  {
    // Spec § "Event name conventions": the router owns one
    // TelegramEventObserver per Bot API update type, plus a separate
    // 'error' observer for the error-propagation channel. Keys are the
    // snake_case wire-level names exactly as Telegram emits them.
    $router = new Router();

    self::assertSame(
      [...Router::UPDATE_TYPES, 'error'],
      array_keys($router->observers),
    );

    foreach ($router->observers as $name => $observer) {
      self::assertInstanceOf(TelegramEventObserver::class, $observer);
      self::assertSame($name, $observer->eventName);
    }
  }

  public function testUpdateTypesConstantMatchesUpdateSchema(): void
  {
    // The 25 update types are derived from regenerated `Types/Update.php`
    // (Phase 2 codegen). Spec § "Event name conventions" lists the priority
    // order; we verify the set + count here. If Phase 2 regenerates Update
    // with a new annotation the diff will surface here.
    self::assertSame(
      [
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
      ],
      Router::UPDATE_TYPES,
    );
    self::assertCount(25, Router::UPDATE_TYPES);
  }

  public function testCamelCasePropertyAliasesPointAtTheSameInstance(): void
  {
    // Spec § "Dispatcher, Router, Filters": observers are accessible both
    // as `$router->editedMessage` (camelCase) and `$router->observers['edited_message']`
    // (snake_case array). Both must return the *same* TelegramEventObserver
    // instance — not two parallel copies.
    $router = new Router();

    self::assertSame($router->observers['message'], $router->message);
    self::assertSame($router->observers['edited_message'], $router->editedMessage);
    self::assertSame($router->observers['channel_post'], $router->channelPost);
    self::assertSame($router->observers['edited_channel_post'], $router->editedChannelPost);
    self::assertSame($router->observers['business_connection'], $router->businessConnection);
    self::assertSame($router->observers['business_message'], $router->businessMessage);
    self::assertSame($router->observers['edited_business_message'], $router->editedBusinessMessage);
    self::assertSame($router->observers['deleted_business_messages'], $router->deletedBusinessMessages);
    self::assertSame($router->observers['guest_message'], $router->guestMessage);
    self::assertSame($router->observers['message_reaction'], $router->messageReaction);
    self::assertSame($router->observers['message_reaction_count'], $router->messageReactionCount);
    self::assertSame($router->observers['inline_query'], $router->inlineQuery);
    self::assertSame($router->observers['chosen_inline_result'], $router->chosenInlineResult);
    self::assertSame($router->observers['callback_query'], $router->callbackQuery);
    self::assertSame($router->observers['shipping_query'], $router->shippingQuery);
    self::assertSame($router->observers['pre_checkout_query'], $router->preCheckoutQuery);
    self::assertSame($router->observers['purchased_paid_media'], $router->purchasedPaidMedia);
    self::assertSame($router->observers['poll'], $router->poll);
    self::assertSame($router->observers['poll_answer'], $router->pollAnswer);
    self::assertSame($router->observers['my_chat_member'], $router->myChatMember);
    self::assertSame($router->observers['chat_member'], $router->chatMember);
    self::assertSame($router->observers['chat_join_request'], $router->chatJoinRequest);
    self::assertSame($router->observers['chat_boost'], $router->chatBoost);
    self::assertSame($router->observers['removed_chat_boost'], $router->removedChatBoost);
    self::assertSame($router->observers['managed_bot'], $router->managedBot);
    self::assertSame($router->observers['error'], $router->errors);
  }

  public function testInitialTreeStateHasNoParentAndNoSubRouters(): void
  {
    // A freshly-constructed router stands alone. include_router builds
    // the tree; cycles/re-parenting are rejected.
    $router = new Router();

    self::assertNull($router->parentRouter);
    self::assertSame([], $router->subRouters);
  }

  public function testIncludeRouterAttachesChildAndSetsParent(): void
  {
    // Linking semantics: parent->subRouters gains the child, child->parentRouter
    // points back. Match upstream's `parent_router` setter which appends to
    // `router.sub_routers`.
    $parent = new Router('parent');
    $child = new Router('child');

    $returned = $parent->includeRouter($child);

    self::assertSame($child, $returned, 'include_router returns the included router for chaining.');
    self::assertSame($parent, $child->parentRouter);
    self::assertSame([$child], $parent->subRouters);
  }

  public function testIncludeRouterRejectsSelf(): void
  {
    // Upstream raises RuntimeError "Self-referencing routers is not allowed";
    // PHP port maps that to LogicException since it's a programming error.
    $router = new Router('me');

    $this->expectException(LogicException::class);
    $this->expectExceptionMessageMatches('/cannot include itself/');

    $router->includeRouter($router);
  }

  public function testIncludeRouterRejectsAlreadyAttachedRouter(): void
  {
    // Upstream raises RuntimeError if `_parent_router` is already set.
    // Re-attaching to a different parent would silently corrupt the
    // upstream's sub_routers chain.
    $rootA = new Router('a');
    $rootB = new Router('b');
    $child = new Router('child');

    $rootA->includeRouter($child);

    $this->expectException(LogicException::class);
    $this->expectExceptionMessageMatches('/already attached/');

    $rootB->includeRouter($child);
  }

  public function testIncludeRouterRejectsCycles(): void
  {
    // Walk-ancestors cycle detection: if A->B->C and we try C->A then
    // A appears in C's ancestor chain. Matches upstream's `while parent` loop.
    $a = new Router('a');
    $b = new Router('b');
    $c = new Router('c');
    $a->includeRouter($b);
    $b->includeRouter($c);

    $this->expectException(LogicException::class);
    $this->expectExceptionMessageMatches('/[Cc]ycle/');

    $c->includeRouter($a);
  }

  public function testIncludeRoutersChainsMultipleAttachments(): void
  {
    // Variadic convenience for attaching several routers at once. Each is
    // validated independently — failure on one short-circuits the rest
    // (matches upstream `for router in routers: self.include_router`).
    $root = new Router('root');
    $a = new Router('a');
    $b = new Router('b');
    $c = new Router('c');

    $returned = $root->includeRouters($a, $b, $c);

    self::assertSame($root, $returned, 'include_routers returns the parent for fluent chaining.');
    self::assertSame([$a, $b, $c], $root->subRouters);
    self::assertSame($root, $a->parentRouter);
    self::assertSame($root, $b->parentRouter);
    self::assertSame($root, $c->parentRouter);
  }

  public function testResolveUsedUpdateTypesReturnsEmptyForEmptyRouter(): void
  {
    // No handlers anywhere in the tree → no update types in use. Matches
    // upstream `resolve_used_update_types` returning an empty list.
    $router = new Router();

    self::assertSame([], $router->resolveUsedUpdateTypes());
  }

  public function testResolveUsedUpdateTypesFindsHandlersOnLocalObservers(): void
  {
    // A registered handler on the local 'message' observer makes 'message'
    // a used update type. Other types stay absent.
    $router = new Router();
    $router->message->register(static fn(): string => 'ok');

    self::assertSame(['message'], $router->resolveUsedUpdateTypes());
  }

  public function testResolveUsedUpdateTypesWalksSubRouters(): void
  {
    // Sub-router handlers contribute to the parent's used set. Order is
    // not guaranteed by upstream (`sorted(handlers_in_use)`); the port
    // de-duplicates via an associative key-set so order matches a depth-
    // first walk of `subRouters`.
    $root = new Router('root');
    $child = new Router('child');
    $grandchild = new Router('grandchild');

    $root->includeRouter($child);
    $child->includeRouter($grandchild);

    $root->message->register(static fn(): string => 'r');
    $child->callbackQuery->register(static fn(): string => 'c');
    $grandchild->inlineQuery->register(static fn(): string => 'g');

    $used = $root->resolveUsedUpdateTypes();
    sort($used);

    self::assertSame(['callback_query', 'inline_query', 'message'], $used);
  }

  public function testResolveUsedUpdateTypesSkipsErrorObserver(): void
  {
    // The 'error' observer is internal (INTERNAL_UPDATE_TYPES in upstream)
    // and never appears in the used-types result, even with handlers.
    $router = new Router();
    $router->errors->register(static fn(): string => 'oops');

    self::assertSame([], $router->resolveUsedUpdateTypes());
  }

  public function testResolveUsedUpdateTypesHonorsSkipEvents(): void
  {
    // The skip list filters update types out of the result. Used by
    // `Dispatcher::startPolling` when the caller wants `allowed_updates`
    // to exclude specific types regardless of registered handlers.
    $router = new Router();
    $router->message->register(static fn(): string => 'm');
    $router->callbackQuery->register(static fn(): string => 'cb');

    $used = $router->resolveUsedUpdateTypes(['message']);
    sort($used);

    self::assertSame(['callback_query'], $used);
  }

  public function testResolveUsedUpdateTypesDeduplicatesAcrossTree(): void
  {
    // If both parent and child register handlers for the same update type,
    // it appears once. The upstream uses a set; the port uses associative
    // array keys to mimic that.
    $root = new Router('root');
    $child = new Router('child');
    $root->includeRouter($child);

    $root->message->register(static fn(): string => 'r');
    $child->message->register(static fn(): string => 'c');

    self::assertSame(['message'], $root->resolveUsedUpdateTypes());
  }

  public function testPropagateEventDispatchesToLocalObserver(): void
  {
    // Happy path: a handler registered on the local observer claims the
    // event. The return value is what propagate_event returns.
    $router = new Router();
    $router->message->register(static fn(): string => 'claimed');

    $result = $router->propagateEvent('message', new Chat(id: 1, type: 'private'));

    self::assertSame('claimed', $result);
  }

  public function testPropagateEventReturnsUnhandledWhenNoHandlersExist(): void
  {
    // Empty tree → UNHANDLED. Upstream returns the sentinel; the port
    // mirrors with `UnhandledSentinel::instance()`.
    $router = new Router();

    self::assertSame(
      UnhandledSentinel::instance(),
      $router->propagateEvent('message', new Chat(id: 1, type: 'private')),
    );
  }

  public function testPropagateEventFallsThroughToSubRouters(): void
  {
    // When the local observer doesn't claim (no handlers, or all return
    // UNHANDLED) the parent recurses into `subRouters` in registration
    // order. The first claiming child wins.
    $root = new Router('root');
    $child = new Router('child');
    $root->includeRouter($child);

    $child->message->register(static fn(): string => 'from_child');

    $result = $root->propagateEvent('message', new Chat(id: 1, type: 'private'));

    self::assertSame('from_child', $result);
  }

  public function testPropagateEventPicksFirstClaimingChild(): void
  {
    // Sub-router iteration is in registration order; the first child to
    // return non-UNHANDLED short-circuits the chain.
    $root = new Router('root');
    $first = new Router('first');
    $second = new Router('second');
    $root->includeRouter($first);
    $root->includeRouter($second);

    $secondCalled = false;
    $first->message->register(static fn(): string => 'first_wins');
    $second->message->register(static function () use (&$secondCalled): string {
      $secondCalled = true;

      return 'second';
    });

    $result = $root->propagateEvent('message', new Chat(id: 1, type: 'private'));

    self::assertSame('first_wins', $result);
    self::assertFalse($secondCalled, 'Second child must not run once first claims the event.');
  }

  public function testPropagateEventInjectsEventRouter(): void
  {
    // Spec § "Injected dispatcher kwargs": before each observer call, the
    // router writes `event_router => $this` so handlers/middlewares can
    // introspect the active router. The child handler must see the *child*
    // router as event_router, not the root — upstream behavior.
    $root = new Router('root');
    $child = new Router('child');
    $root->includeRouter($child);

    $observed = null;
    $child->message->register(static function (Router $event_router) use (&$observed): string {
      $observed = $event_router;

      return 'ok';
    });

    $root->propagateEvent('message', new Chat(id: 1, type: 'private'));

    self::assertSame($child, $observed, 'event_router should be the router that claimed the event.');
  }

  public function testPropagateEventForwardsCallerKwargs(): void
  {
    // External kwargs (the dispatcher's context bag) flow through
    // propagate_event unchanged into handler invocations.
    $router = new Router();
    $observed = null;
    $router->message->register(static function (string $bot) use (&$observed): string {
      $observed = $bot;

      return 'ok';
    });

    $router->propagateEvent(
      'message',
      new Chat(id: 1, type: 'private'),
      ['bot' => 'fake_bot'],
    );

    self::assertSame('fake_bot', $observed);
  }

  public function testPropagateEventThrowsOnUnknownUpdateType(): void
  {
    // The update type must be in the observers map (24 wire types + 'error').
    // An unknown key is a programming error — upstream's `observers.get(type)`
    // returns None and treats it as no-op; the port is stricter and throws
    // because we have a finite, schema-derived set.
    $router = new Router();

    $this->expectException(LogicException::class);
    $this->expectExceptionMessageMatches('/[Uu]nknown update type/');

    $router->propagateEvent('not_a_real_type', new Chat(id: 1, type: 'private'));
  }

  public function testEmitStartupTriggersOwnAndSubRouterStartups(): void
  {
    // Lifecycle fan-out is depth-first: own startup observer fires first,
    // then each sub-router's, recursively. Matches upstream `emit_startup`.
    $root = new Router('root');
    $child = new Router('child');
    $grandchild = new Router('grandchild');
    $root->includeRouter($child);
    $child->includeRouter($grandchild);

    $order = [];
    $root->startup->register(static function () use (&$order): void {
      $order[] = 'root';
    });
    $child->startup->register(static function () use (&$order): void {
      $order[] = 'child';
    });
    $grandchild->startup->register(static function () use (&$order): void {
      $order[] = 'grandchild';
    });

    $root->emitStartup();

    self::assertSame(['root', 'child', 'grandchild'], $order);
  }

  public function testEmitShutdownTriggersOwnAndSubRouterShutdowns(): void
  {
    // Mirror of emitStartup for the teardown path.
    $root = new Router('root');
    $child = new Router('child');
    $root->includeRouter($child);

    $order = [];
    $root->shutdown->register(static function () use (&$order): void {
      $order[] = 'root';
    });
    $child->shutdown->register(static function () use (&$order): void {
      $order[] = 'child';
    });

    $root->emitShutdown();

    self::assertSame(['root', 'child'], $order);
  }

  public function testEmitStartupInjectsRouterKwarg(): void
  {
    // Spec § "Injected dispatcher kwargs": startup/shutdown handlers can
    // declare `Router $router` and receive the *emitting* router. Each
    // recursion overwrites the kwarg with that level's router, so the
    // child startup callback sees the child — not the root.
    $root = new Router('root');
    $child = new Router('child');
    $root->includeRouter($child);

    $observed = null;
    $child->startup->register(static function (Router $router) use (&$observed): void {
      $observed = $router;
    });

    $root->emitStartup();

    self::assertSame($child, $observed);
  }

  public function testEmitShutdownInjectsRouterKwarg(): void
  {
    // Symmetric assertion for emit_shutdown.
    $root = new Router('root');
    $child = new Router('child');
    $root->includeRouter($child);

    $observed = null;
    $child->shutdown->register(static function (Router $router) use (&$observed): void {
      $observed = $router;
    });

    $root->emitShutdown();

    self::assertSame($child, $observed);
  }

  public function testEmitStartupForwardsCallerKwargs(): void
  {
    // Workflow data passed by the dispatcher (e.g. `bot`) is forwarded to
    // every startup callback in the tree.
    $root = new Router('root');
    $child = new Router('child');
    $root->includeRouter($child);

    $observed = null;
    $child->startup->register(static function (string $bot) use (&$observed): void {
      $observed = $bot;
    });

    $root->emitStartup(['bot' => 'shared_bot']);

    self::assertSame('shared_bot', $observed);
  }

  public function testRouterIsNotFinal(): void
  {
    // Dispatcher extends Router (Task 3.10). The class must therefore NOT
    // be declared `final`. Reflection is the most direct check.
    $reflection = new ReflectionClass(Router::class);

    self::assertFalse($reflection->isFinal(), 'Router must be subclass-able (Dispatcher extends it).');
  }

  public function testPropagateEventOuterMiddlewareWrapsSubRouterDispatch(): void
  {
    // Fix I2: the local observer's outer middleware must wrap the ENTIRE
    // dispatch (local observer + sub-router recursion). Upstream's
    // `propagate_event` calls `observer.wrap_outer_middleware(_wrapped, ...)`
    // where `_wrapped` invokes `_propagate_event` which contains the
    // sub-router walk (`router.py:152-166`). The port mirrors this by
    // wrapping `Router::propagateEvent`'s entire body with the local
    // observer's outer chain — so an outer middleware on the parent's
    // `message` observer wraps the child router's claiming handler too.
    //
    // Before the fix the outer chain wrapped only `TelegramEventObserver::trigger`
    // (the local-observer dispatch), meaning a parent's outer middleware
    // fired BEFORE the sub-router walk and unwound BEFORE the child's
    // handler ran. The child-handler marker must sit between the spy's
    // before/after markers to prove the spy's scope.
    $root = new Router('root');
    $child = new Router('child');
    $root->includeRouter($child);

    $log = [];

    // Only the child registers a handler — the local observer on root
    // returns UNHANDLED so the sub-router recursion is the only path.
    $child->message->register(static function () use (&$log): string {
      $log[] = 'child-handler';

      return 'claimed-by-child';
    });

    $spy = new class ($log) extends BaseMiddleware {
      /** @param list<string> $log */
      public function __construct(public array &$log) {}

      public function __invoke(Closure $handler, object $event, array $data): mixed
      {
        $this->log[] = 'parent-outer-before';
        $result = $handler($event, $data);
        $this->log[] = 'parent-outer-after';

        return $result;
      }
    };
    $root->message->outerMiddleware($spy);

    $result = $root->propagateEvent('message', new Chat(id: 1, type: 'private'));

    self::assertSame('claimed-by-child', $result);
    self::assertSame(
      ['parent-outer-before', 'child-handler', 'parent-outer-after'],
      $log,
      'Parent observer outer middleware must wrap the full dispatch including sub-routers.',
    );
  }

  public function testPropagateEventOuterMiddlewareCanShortCircuitSubRouters(): void
  {
    // Parity guard with the upstream wrap shape: an outer middleware that
    // does NOT call $handler MUST short-circuit the entire dispatch
    // (local observer AND sub-routers). This proves the outer middleware
    // wraps the whole `_wrapped` callable, not just the local observer's
    // trigger.
    $root = new Router('root');
    $child = new Router('child');
    $root->includeRouter($child);

    $childCalled = false;
    $child->message->register(static function () use (&$childCalled): string {
      $childCalled = true;

      return 'unreachable';
    });

    $root->message->outerMiddleware(new class extends BaseMiddleware {
      public function __invoke(Closure $handler, object $event, array $data): mixed
      {
        return 'short_circuit';
      }
    });

    $result = $root->propagateEvent('message', new Chat(id: 1, type: 'private'));

    self::assertSame('short_circuit', $result);
    self::assertFalse(
      $childCalled,
      'Outer middleware short-circuit must prevent the sub-router walk too.',
    );
  }

  /**
   * Upstream `test_router.py::TestRouter::test_including_many_routers_bad_type`
   * asserts that `include_routers()` (zero arguments) raises. PHP's variadic
   * signature accepts zero-args silently — the method just becomes a no-op.
   * Upstream's contract (at least one router required) does NOT apply here
   * because the PHP signature is `Router ...$routers`; calling with no args
   * is valid and simply returns `$this`. Documenting the intentional divergence.
   *
   * Upstream `test_router.py::TestRouter::test_include_router_by_string_bad_type`
   * is enforced statically by PHP's type system — passing a non-Router to
   * `includeRouter(Router $router)` triggers a fatal `TypeError` at the PHP
   * engine level, with no need for a runtime check or a test. PHPStan level 9
   * catches the caller-side mistake before runtime.
   */
  public function testIncludeRoutersWithZeroArgumentsIsNoOp(): void
  {
    // Upstream requires at least one router argument and raises ValueError for
    // zero args. PHP variadic zero-args is a no-op; the method just returns
    // `$this`. Verify the post-condition is clean (no sub-routers added).
    $router = new Router('root');

    $returned = $router->includeRouters();

    self::assertSame($router, $returned, 'includeRouters() with zero args returns $this for fluent chaining.');
    self::assertSame([], $router->subRouters, 'No sub-routers must be added for a zero-args call.');
  }

  /**
   * Upstream `test_dispatcher.py::TestDispatcher::test_specify_updates_calculation`
   * verifies that `resolveUsedUpdateTypes()` correctly reflects the growing
   * tree as routers are attached and handlers registered.
   *
   * Partially covered by `testResolveUsedUpdateTypesWalksSubRouters` and
   * `testResolveUsedUpdateTypesDeduplicatesAcrossTree`. This test adds the
   * **incremental-attachment progression** shape from the upstream test:
   * starting from a single handler, verify that new types appear only after
   * new routers are attached, and that a sub-sub-router contributes its
   * types to the root.
   */
  public function testResolveUsedUpdateTypesGrowsAsRoutersAttached(): void
  {
    // Step 0: root has only a message handler.
    $dispatcher = new Router('root');
    $dispatcher->message->register(static fn(): string => 'ok');

    self::assertSame(['message'], $dispatcher->resolveUsedUpdateTypes());

    // Step 1: attach a child with a callback_query handler.
    $router1 = new Router('r1');
    $router1->callbackQuery->register(static fn(): string => 'ok');
    $dispatcher->includeRouter($router1);

    $types2 = $dispatcher->resolveUsedUpdateTypes();
    sort($types2);
    self::assertSame(['callback_query', 'message'], $types2);

    // Step 2: attach a second child with a poll handler.
    $router2 = new Router('r2');
    $router2->poll->register(static fn(): string => 'ok');
    $dispatcher->includeRouter($router2);

    $types3 = $dispatcher->resolveUsedUpdateTypes();
    sort($types3);
    self::assertSame(['callback_query', 'message', 'poll'], $types3);

    // Step 3: attach a grandchild with an edited_message handler under router2.
    $router21 = new Router('r21');
    $router21->editedMessage->register(static fn(): string => 'ok');
    $router2->includeRouter($router21);

    $types4 = $dispatcher->resolveUsedUpdateTypes();
    sort($types4);
    self::assertSame(['callback_query', 'edited_message', 'message', 'poll'], $types4);

    // Sub-tree from router2 alone also reports correctly.
    $types5 = $router2->resolveUsedUpdateTypes();
    sort($types5);
    self::assertSame(['edited_message', 'poll'], $types5);
  }

  /**
   * Upstream `test_router.py::TestRouter::test_global_filter_in_nested_router`
   * verifies that a global filter on the parent's `message` observer that
   * returns False causes `r1.propagate_event(...)` to return `UNHANDLED`.
   *
   * **phpbotgram behavior note (Fix I6 divergence)**:
   * In upstream `aiogram`, when `r1.message.trigger()` returns `REJECTED` (due
   * to the global filter), `_propagate_event` immediately returns `UNHANDLED`
   * — the sub-router walk does NOT happen.
   *
   * In phpbotgram, Fix I6 converts `RejectedSentinel` → `UnhandledSentinel`
   * before the sub-router walk. This means a global filter on the **parent's**
   * local observer rejects only the parent's local handlers; sub-router handlers
   * on the same event type are still reachable.
   *
   * This test documents the actual phpbotgram behavior:
   * - Parent global filter that returns false → parent local observer returns
   *   `RejectedSentinel` → collapsed to `UnhandledSentinel` by Fix I6 → sub-
   *   router handler is reached → handler result is returned.
   *
   * To gate sub-routers, use outer middleware (which wraps both local observer
   * AND sub-router recursion — see `testPropagateEventOuterMiddlewareWrapsSubRouterDispatch`).
   */
  public function testGlobalFilterOnParentLocalObserverDoesNotGateSubRouterHandlers(): void
  {
    // phpbotgram behavior: parent global filter only gates local observer handlers.
    // Sub-router handlers bypass the parent's global filter because Fix I6
    // collapses RejectedSentinel to UnhandledSentinel before the sub-router walk.
    $r1 = new Router('r1');
    $r2 = new Router('r2');
    $r1->includeRouter($r2);

    $r1->message->filter(static fn(): bool => false);

    // Sub-router handler — reached because Fix I6 converts REJECTED to UNHANDLED.
    $r2->message->register(static fn(): string => 'sub_router_reached');

    $result = $r1->propagateEvent('message', new Chat(id: 1, type: 'private'));

    self::assertSame(
      'sub_router_reached',
      $result,
      'phpbotgram (Fix I6): parent global filter does not gate sub-router handlers; '
      . 'use outer middleware to gate the full dispatch tree.',
    );
  }
}
