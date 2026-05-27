<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Dispatcher\Event;

use Closure;
use Gruven\PhpBotGram\Dispatcher\Event\Bases;
use Gruven\PhpBotGram\Dispatcher\Event\FilterObject;
use Gruven\PhpBotGram\Dispatcher\Event\HandlerObject;
use Gruven\PhpBotGram\Dispatcher\Event\RejectedSentinel;
use Gruven\PhpBotGram\Dispatcher\Event\TelegramEventObserver;
use Gruven\PhpBotGram\Dispatcher\Event\UnhandledSentinel;
use Gruven\PhpBotGram\Dispatcher\Flags\Flag;
use Gruven\PhpBotGram\Dispatcher\Flags\FlagDecorator;
use Gruven\PhpBotGram\Dispatcher\Middlewares\BaseMiddleware;
use Gruven\PhpBotGram\Dispatcher\Middlewares\MiddlewareManager;
use Gruven\PhpBotGram\Dispatcher\Router;
use Gruven\PhpBotGram\Filters\Command;
use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\TelegramObject;
use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilter;
use PHPUnit\Framework\TestCase;

use const Gruven\PhpBotGram\F;

/**
 * @internal
 */
final class TelegramEventObserverTest extends TestCase
{
  protected function setUp(): void
  {
    // Reset the WeakMap-backed flag store so imperative attachments from a
    // previous test cannot leak into reflection-based flag extraction.
    FlagDecorator::reset();
  }

  public function testConstructionDefaults(): void
  {
    // The observer is identified by an event_name string ('message',
    // 'callback_query', etc.) and starts empty: no handlers, no global
    // filters. Mirrors upstream `TelegramEventObserver(router, event_name)`.
    $observer = new TelegramEventObserver('message');

    self::assertSame('message', $observer->eventName);
    self::assertSame([], $observer->handlers);
    self::assertSame([], $observer->filters);
  }

  public function testRegisterAddsHandlerWithNoFiltersOrFlags(): void
  {
    // Default: registering a bare callback creates a HandlerObject with an
    // empty filter list and an empty flags map. Returns the original
    // callback unchanged so the call site can keep a reference.
    $observer = new TelegramEventObserver('message');
    $callback = static fn(): string => 'ok';

    $returned = $observer->register($callback);

    self::assertSame($callback, $returned);
    self::assertCount(1, $observer->handlers);
    self::assertInstanceOf(HandlerObject::class, $observer->handlers[0]);
    self::assertSame($callback, $observer->handlers[0]->callback);
    self::assertSame([], $observer->handlers[0]->filters);
    self::assertSame([], $observer->handlers[0]->flags);
  }

  public function testRegisterStoresFiltersAndFlags(): void
  {
    // Filters list-shape: each Closure becomes a FilterObject. Flags are
    // stored verbatim on the HandlerObject. Multiple handlers append in
    // registration order.
    $observer = new TelegramEventObserver('message');
    $callback = static fn(): string => 'ok';
    $filter1 = static fn(): bool => true;
    $filter2 = static fn(): bool => true;

    $observer->register($callback, [$filter1, $filter2], ['admin_only' => true]);

    self::assertCount(1, $observer->handlers);
    $handler = $observer->handlers[0];
    self::assertCount(2, $handler->filters);
    self::assertInstanceOf(FilterObject::class, $handler->filters[0]);
    self::assertInstanceOf(FilterObject::class, $handler->filters[1]);
    self::assertSame($filter1, $handler->filters[0]->callback);
    self::assertSame($filter2, $handler->filters[1]->callback);
    self::assertSame(['admin_only' => true], $handler->flags);
  }

  public function testRegisterMergesAttributeFlagsWithManualFlagsManualWins(): void
  {
    // Attribute flags (read via Flags::extractFlags) are merged with the
    // manual flags map at registration time. Conflict resolution: manual
    // wins. Matches upstream `flags = {**extract_flags_from_object(cb), **flags}`.
    //
    // We attach the imperative flag via FlagDecorator (the closure-attribute
    // path is awkward in inline tests). Flags::extractFlags treats imperative
    // and attribute attachments identically here.
    $observer = new TelegramEventObserver('message');
    $callback = static fn(): string => 'ok';
    FlagDecorator::attach($callback, new Flag('admin_only', false));
    FlagDecorator::attach($callback, new Flag('rate_limit', 5));

    $observer->register($callback, [], ['admin_only' => true, 'priority' => 10]);

    $handler = $observer->handlers[0];
    self::assertSame(true, $handler->flags['admin_only'], 'Manual flag must override attribute flag of the same name.');
    self::assertSame(5, $handler->flags['rate_limit']);
    self::assertSame(10, $handler->flags['priority']);
  }

  public function testFilterAddsGlobalFiltersInRegistrationOrder(): void
  {
    // The `filter()` method appends global filters that gate every handler
    // on this observer. Each closure is wrapped in a FilterObject; multiple
    // calls accumulate.
    $observer = new TelegramEventObserver('message');
    $f1 = static fn(): bool => true;
    $f2 = static fn(): bool => true;
    $f3 = static fn(): bool => true;

    $observer->filter($f1, $f2);
    $observer->filter($f3);

    self::assertCount(3, $observer->filters);
    self::assertSame($f1, $observer->filters[0]->callback);
    self::assertSame($f2, $observer->filters[1]->callback);
    self::assertSame($f3, $observer->filters[2]->callback);
  }

  public function testTriggerWithNoHandlersReturnsUnhandled(): void
  {
    // The empty observer has nothing to dispatch — every event is
    // UNHANDLED. Matches upstream's `return UNHANDLED` fall-through.
    $observer = new TelegramEventObserver('message');
    $event = new Chat(id: 1, type: 'private');

    $result = $observer->trigger($event);

    self::assertSame(UnhandledSentinel::instance(), $result);
  }

  public function testTriggerWithOnePassingHandlerReturnsHandlerResult(): void
  {
    // A handler with no filters always accepts. Its return value becomes
    // the observer's `trigger()` return value.
    $observer = new TelegramEventObserver('message');
    $observer->register(static fn(): string => 'handled');

    $result = $observer->trigger(new Chat(id: 1, type: 'private'));

    self::assertSame('handled', $result);
  }

  public function testTriggerHandlerReturningUnhandledFallsThroughToNext(): void
  {
    // A handler can explicitly opt out by returning UnhandledSentinel; the
    // observer continues iterating handlers in registration order. The
    // second handler's return value becomes the final result.
    $observer = new TelegramEventObserver('message');
    $observer->register(static fn(): UnhandledSentinel => UnhandledSentinel::instance());
    $observer->register(static fn(): string => 'second');

    $result = $observer->trigger(new Chat(id: 1, type: 'private'));

    self::assertSame('second', $result);
  }

  public function testTriggerHandlerWhoseFilterRejectsFallsThroughToNext(): void
  {
    // When a handler's filter pipeline returns `false`, the handler itself
    // is not invoked and dispatch continues with the next handler. The
    // rejected handler's callback must not run.
    $observer = new TelegramEventObserver('message');
    $firstCalled = false;
    $observer->register(
      static function () use (&$firstCalled): string {
        $firstCalled = true;

        return 'first';
      },
      [static fn(): bool => false],
    );
    $observer->register(static fn(): string => 'second');

    $result = $observer->trigger(new Chat(id: 1, type: 'private'));

    self::assertSame('second', $result);
    self::assertFalse($firstCalled, 'Filter-rejected handler must not execute.');
  }

  public function testTriggerGlobalFilterRejectionShortCircuitsWithRejectedSentinel(): void
  {
    // A `false` from any global filter aborts dispatch immediately and
    // returns RejectedSentinel — no handlers are tried. The unreachable
    // handler must not run.
    $observer = new TelegramEventObserver('message');
    $handlerCalled = false;
    $observer->filter(static fn(): bool => false);
    $observer->register(static function () use (&$handlerCalled): string {
      $handlerCalled = true;

      return 'unreachable';
    });

    $result = $observer->trigger(new Chat(id: 1, type: 'private'));

    self::assertSame(RejectedSentinel::instance(), $result);
    self::assertFalse($handlerCalled);
  }

  public function testTriggerGlobalFilterMergesArrayResultIntoHandlerKwargs(): void
  {
    // A global filter returning an associative array is treated as "accept
    // + inject these kwargs". Subsequent handlers see the merged kwargs.
    // Verifies the cascade described in HandlerObject::check, applied at
    // the observer level.
    $observer = new TelegramEventObserver('message');
    $observer->filter(static fn(): array => ['injected' => 'from_filter']);
    $observed = null;
    $observer->register(static function (string $injected) use (&$observed): string {
      $observed = $injected;

      return 'ok';
    });

    $result = $observer->trigger(new Chat(id: 1, type: 'private'));

    self::assertSame('ok', $result);
    self::assertSame('from_filter', $observed);
  }

  public function testTriggerHandlerFilterReturnsArrayAndHandlerReceivesValue(): void
  {
    // Per-handler filter result threading: filter returns ['user_id' => 42]
    // and the handler declares `int $user_id` as a parameter. The reflection
    // adapter binds it. This is the canonical kwarg-injection flow.
    $observer = new TelegramEventObserver('message');
    $captured = null;
    $observer->register(
      static function (int $user_id) use (&$captured): string {
        $captured = $user_id;

        return 'received';
      },
      [static fn(): array => ['user_id' => 42]],
    );

    $result = $observer->trigger(new Chat(id: 1, type: 'private'));

    self::assertSame('received', $result);
    self::assertSame(42, $captured);
  }

  public function testInvokeAsDecoratorFactoryRegistersWhenCallbackProvided(): void
  {
    // The factory form `$observer(filters: [...], flags: [...])` returns a
    // registration closure: pass it the callback to register it.
    $observer = new TelegramEventObserver('message');
    $callback = static fn(): string => 'decorated';
    $filter = static fn(): bool => true;

    $register = $observer(filters: [$filter], flags: ['key' => 'value']);
    self::assertInstanceOf(Closure::class, $register);
    $returned = $register($callback);

    self::assertSame($callback, $returned);
    self::assertCount(1, $observer->handlers);
    self::assertSame($callback, $observer->handlers[0]->callback);
    self::assertCount(1, $observer->handlers[0]->filters);
    self::assertSame(['key' => 'value'], $observer->handlers[0]->flags);
  }

  public function testInvokeEagerRegistersImmediatelyWhenCallbackProvided(): void
  {
    // Eager form: `$observer($cb, filters: [...], flags: [...])` registers
    // directly and returns the original callback (matching `register()`).
    $observer = new TelegramEventObserver('message');
    $callback = static fn(): string => 'eager';

    $returned = $observer($callback, flags: ['priority' => 1]);

    self::assertSame($callback, $returned);
    self::assertCount(1, $observer->handlers);
    self::assertSame($callback, $observer->handlers[0]->callback);
    self::assertSame(['priority' => 1], $observer->handlers[0]->flags);
  }

  public function testClearEmptiesHandlersAndFilters(): void
  {
    // After clear() both lists are empty and subsequent trigger() returns
    // UNHANDLED. Useful for router rebuilds in tests.
    $observer = new TelegramEventObserver('message');
    $observer->register(static fn(): string => 'x');
    $observer->filter(static fn(): bool => true);

    $observer->clear();

    self::assertSame([], $observer->handlers);
    self::assertSame([], $observer->filters);
    self::assertSame(
      UnhandledSentinel::instance(),
      $observer->trigger(new Chat(id: 1, type: 'private')),
    );
  }

  public function testTriggerExposesEventAsKwargAndHandlerInjection(): void
  {
    // The dispatcher contract: handlers and filters may declare the
    // `event` and `handler` named kwargs. Match upstream's
    // `kwargs["handler"] = handler` injection before each handler's check.
    $observer = new TelegramEventObserver('message');
    $observedEvent = null;
    $observedHandler = null;
    $observer->register(
      static function (TelegramObject $event, HandlerObject $handler) use (
        &$observedEvent,
        &$observedHandler,
      ): string {
        $observedEvent = $event;
        $observedHandler = $handler;

        return 'ok';
      },
    );

    $event = new Chat(id: 5, type: 'private');
    $observer->trigger($event);

    self::assertSame($event, $observedEvent);
    self::assertInstanceOf(HandlerObject::class, $observedHandler);
    self::assertSame($observer->handlers[0], $observedHandler);
  }

  public function testTriggerMergesCallerKwargsIntoHandlerInvocation(): void
  {
    // External kwargs passed to trigger() (the dispatcher's context bag in
    // production) flow into the handler's parameter binding. Verifies the
    // `$kwargs` second arg threads through to handler-declared params.
    $observer = new TelegramEventObserver('message');
    $observed = null;
    $observer->register(static function (string $bot) use (&$observed): string {
      $observed = $bot;

      return 'ok';
    });

    $observer->trigger(new Chat(id: 1, type: 'private'), ['bot' => 'fake_bot']);

    self::assertSame('fake_bot', $observed);
  }

  public function testConstructionExposesEmptyOuterAndInnerMiddlewareManagers(): void
  {
    // Spec § "Dispatcher" requires every TelegramEventObserver to own two
    // independent MiddlewareManagers: outer (wraps the whole observer) and
    // inner (wraps each handler call). Both start empty.
    $observer = new TelegramEventObserver('message');

    self::assertInstanceOf(MiddlewareManager::class, $observer->outerMiddleware);
    self::assertInstanceOf(MiddlewareManager::class, $observer->innerMiddleware);
    self::assertCount(0, $observer->outerMiddleware);
    self::assertCount(0, $observer->innerMiddleware);
    self::assertNotSame(
      $observer->outerMiddleware,
      $observer->innerMiddleware,
      'Outer and inner managers must be distinct instances.',
    );
  }

  public function testOuterMiddlewareHelperAppendsToOuterManager(): void
  {
    // The helper exists so Dispatcher's setup code reads
    // `$observer->outerMiddleware(new …)` (matching upstream's call site)
    // rather than threading through ->outerMiddleware->register(...).
    $observer = new TelegramEventObserver('message');
    $mw = self::passthroughMiddleware();

    $returned = $observer->outerMiddleware($mw);

    self::assertSame($mw, $returned);
    self::assertCount(1, $observer->outerMiddleware);
    self::assertSame($mw, $observer->outerMiddleware[0]);
    self::assertCount(0, $observer->innerMiddleware, 'inner manager must not be touched.');
  }

  public function testInnerMiddlewareHelperAppendsToInnerManager(): void
  {
    // Mirror of outerMiddleware() for the per-handler chain.
    $observer = new TelegramEventObserver('message');
    $mw = self::passthroughMiddleware();

    $returned = $observer->innerMiddleware($mw);

    self::assertSame($mw, $returned);
    self::assertCount(1, $observer->innerMiddleware);
    self::assertSame($mw, $observer->innerMiddleware[0]);
    self::assertCount(0, $observer->outerMiddleware, 'outer manager must not be touched.');
  }

  public function testOuterMiddlewareWrapsEntireObserverIncludingGlobalFilters(): void
  {
    // Outer middleware wraps the *entire* observer dispatch (filter chain
    // + handler iteration) PLUS, when invoked through `Router::propagateEvent`,
    // the depth-first sub-router walk. The middleware sees the event before
    // the global filters even run, and can short-circuit the whole thing.
    //
    // Fix I2: outer middleware composition lives on `Router::propagateEvent`
    // (mirrors upstream's `wrap_outer_middleware(_wrapped, ...)` shape) — a
    // bare `$observer->trigger()` call is the "raw" path that skips the
    // outer wrap entirely. Verifying the wrap shape therefore goes through
    // a Router.
    $router = new Router();
    $observer = $router->message;
    $log = [];
    $observer->outerMiddleware(new class ($log) extends BaseMiddleware {
      /** @param list<string> $log */
      public function __construct(public array &$log) {}

      public function __invoke(Closure $handler, object $event, array $data): mixed
      {
        $this->log[] = 'outer-before';
        $result = $handler($event, $data);
        $this->log[] = 'outer-after';

        return $result;
      }
    });
    $observer->filter(static function () use (&$log): bool {
      $log[] = 'global-filter';

      return true;
    });
    $observer->register(static function () use (&$log): string {
      $log[] = 'handler';

      return 'ok';
    });

    $result = $router->propagateEvent('message', new Chat(id: 1, type: 'private'));

    self::assertSame('ok', $result);
    self::assertSame(
      ['outer-before', 'global-filter', 'handler', 'outer-after'],
      $log,
    );
  }

  public function testOuterMiddlewareCanShortCircuitWithoutInvokingObserver(): void
  {
    // An outer middleware that doesn't call $handler skips global filters,
    // per-handler checks, and the handler itself. Useful for global
    // throttling / auth gates that should reject before any observer work.
    // Driven through `Router::propagateEvent` because that's where the
    // outer wrap lives after Fix I2 (raw `trigger()` is outer-middleware-
    // free for upstream parity).
    $router = new Router();
    $observer = $router->message;
    $observer->outerMiddleware(new class extends BaseMiddleware {
      public function __invoke(Closure $handler, object $event, array $data): mixed
      {
        return 'short_circuited';
      }
    });
    $observer->register(static fn(): string => 'unreachable');

    $result = $router->propagateEvent('message', new Chat(id: 1, type: 'private'));

    self::assertSame('short_circuited', $result);
  }

  public function testRawTriggerSkipsOuterMiddleware(): void
  {
    // Fix I2 parity check: `TelegramEventObserver::trigger()` is the "raw"
    // dispatch primitive — global filters + handler iteration + inner
    // middleware, but NO outer middleware. Upstream's
    // `TelegramEventObserver.trigger` (`telegram.py:111-130`) has the
    // same shape; outer middleware is composed by `Router.propagate_event`
    // (`router.py:152-166`) via `observer.wrap_outer_middleware(...)`.
    //
    // The bug this guards against: re-introducing outer-middleware wrapping
    // into `trigger()` would cause double-wrapping in a multi-router tree
    // (parent outer + child trigger's outer = two firings of the same
    // middleware when the dispatch recurses into a child observer that
    // shares a middleware instance through `chain_head` inheritance).
    $observer = new TelegramEventObserver('message');
    $outerCalled = false;
    $observer->outerMiddleware(new class ($outerCalled) extends BaseMiddleware {
      public function __construct(public bool &$called) {}

      public function __invoke(Closure $handler, object $event, array $data): mixed
      {
        $this->called = true;

        return $handler($event, $data);
      }
    });
    $observer->register(static fn(): string => 'handler-result');

    $result = $observer->trigger(new Chat(id: 1, type: 'private'));

    self::assertSame('handler-result', $result);
    self::assertFalse(
      $outerCalled,
      'Raw trigger() must not apply outer middleware (Fix I2: wrapping lives in Router::propagateEvent).',
    );
  }

  public function testInnerMiddlewareWrapsEachIndividualHandlerCall(): void
  {
    // Inner middleware runs once **per claiming handler invocation** — it
    // sits between the per-handler filter check and the actual call. The
    // global filter chain runs once before any handler is considered, so
    // an inner middleware that records the order shows
    // "global → check → inner-before → handler → inner-after".
    $observer = new TelegramEventObserver('message');
    $log = [];
    $observer->innerMiddleware(new class ($log) extends BaseMiddleware {
      /** @param list<string> $log */
      public function __construct(public array &$log) {}

      public function __invoke(Closure $handler, object $event, array $data): mixed
      {
        $this->log[] = 'inner-before';
        $result = $handler($event, $data);
        $this->log[] = 'inner-after';

        return $result;
      }
    });
    $observer->filter(static function () use (&$log): bool {
      $log[] = 'global';

      return true;
    });
    $observer->register(
      static function () use (&$log): string {
        $log[] = 'handler';

        return 'ok';
      },
      [static function () use (&$log): bool {
        $log[] = 'check';

        return true;
      }],
    );

    $observer->trigger(new Chat(id: 1, type: 'private'));

    self::assertSame(
      ['global', 'check', 'inner-before', 'handler', 'inner-after'],
      $log,
    );
  }

  public function testInnerMiddlewareDoesNotRunWhenHandlerFilterRejects(): void
  {
    // Inner middleware runs only AFTER the per-handler filter check accepts.
    // A filter-rejected handler must not see the inner chain — otherwise a
    // logging middleware would emit noise for handlers that didn't actually
    // run.
    $observer = new TelegramEventObserver('message');
    $innerCalled = false;
    $observer->innerMiddleware(new class ($innerCalled) extends BaseMiddleware {
      public function __construct(public bool &$called) {}

      public function __invoke(Closure $handler, object $event, array $data): mixed
      {
        $this->called = true;

        return $handler($event, $data);
      }
    });
    $observer->register(
      static fn(): string => 'unreachable',
      [static fn(): bool => false],
    );

    $result = $observer->trigger(new Chat(id: 1, type: 'private'));

    self::assertSame(UnhandledSentinel::instance(), $result);
    self::assertFalse($innerCalled, 'Inner middleware must not run when handler filter rejects.');
  }

  public function testInnerMiddlewareRunsOncePerCandidateHandler(): void
  {
    // When the first handler returns UNHANDLED and dispatch falls through
    // to the next handler, the inner middleware runs again for the second
    // call — once per claiming candidate. Verifies the chain isn't cached
    // across handlers.
    $observer = new TelegramEventObserver('message');
    $count = 0;
    $observer->innerMiddleware(new class ($count) extends BaseMiddleware {
      public function __construct(public int &$count) {}

      public function __invoke(Closure $handler, object $event, array $data): mixed
      {
        ++$this->count;

        return $handler($event, $data);
      }
    });
    $observer->register(static fn(): UnhandledSentinel => UnhandledSentinel::instance());
    $observer->register(static fn(): string => 'second');

    $observer->trigger(new Chat(id: 1, type: 'private'));

    self::assertSame(2, $count);
  }

  public function testInnerMiddlewareInheritedFromParentRouter(): void
  {
    // Fix C2 regression: inner middleware registered on a parent router's
    // observer must wrap handlers registered on a child router's observer
    // for the same event name. Upstream `_resolve_middlewares` walks
    // `router.chain_head` (= router + ancestors → root) and collects every
    // ancestor's `observers[event_name].middlewares` into one composed
    // chain. The port mirrors that via TelegramEventObserver::$router
    // back-reference + `resolveMiddlewares()`.
    //
    // Test shape: parent router has an inner middleware on its `message`
    // observer; child router (attached via includeRouter) has a handler on
    // its own `message` observer. When the child dispatches, the parent's
    // middleware MUST wrap the child's handler.
    $parent = new Router('parent');
    $child = new Router('child');
    $parent->includeRouter($child);

    $log = [];
    $parent->message->innerMiddleware(new class ($log) extends BaseMiddleware {
      /** @param list<string> $log */
      public function __construct(public array &$log) {}

      public function __invoke(Closure $handler, object $event, array $data): mixed
      {
        $this->log[] = 'parent-mw-before';
        $result = $handler($event, $data);
        $this->log[] = 'parent-mw-after';

        return $result;
      }
    });
    $child->message->register(static function () use (&$log): string {
      $log[] = 'child-handler';

      return 'claimed';
    });

    $result = $child->message->trigger(new Chat(id: 1, type: 'private'));

    self::assertSame('claimed', $result);
    self::assertSame(['parent-mw-before', 'child-handler', 'parent-mw-after'], $log);
  }

  public function testInnerMiddlewareInheritanceComposesInRegistrationOrder(): void
  {
    // When both parent and child have inner middleware on the same event,
    // they compose self-first then ancestors (matching upstream's
    // `reversed(tuple(self.router.chain_head))` walk). With the parent
    // attached first, the resolved chain is [parent-mw, child-mw],
    // executing parent-mw → child-mw → handler → child-mw-after → parent-mw-after.
    $parent = new Router('parent');
    $child = new Router('child');
    $parent->includeRouter($child);

    $log = [];
    $parent->message->innerMiddleware(new class ($log) extends BaseMiddleware {
      /** @param list<string> $log */
      public function __construct(public array &$log) {}

      public function __invoke(Closure $handler, object $event, array $data): mixed
      {
        $this->log[] = 'parent-before';
        $r = $handler($event, $data);
        $this->log[] = 'parent-after';

        return $r;
      }
    });
    $child->message->innerMiddleware(new class ($log) extends BaseMiddleware {
      /** @param list<string> $log */
      public function __construct(public array &$log) {}

      public function __invoke(Closure $handler, object $event, array $data): mixed
      {
        $this->log[] = 'child-before';
        $r = $handler($event, $data);
        $this->log[] = 'child-after';

        return $r;
      }
    });
    $child->message->register(static function () use (&$log): string {
      $log[] = 'handler';

      return 'ok';
    });

    $child->message->trigger(new Chat(id: 1, type: 'private'));

    self::assertSame(
      ['parent-before', 'child-before', 'handler', 'child-after', 'parent-after'],
      $log,
    );
  }

  public function testSkipHandlerContinuesToNextHandlerOnSameObserver(): void
  {
    // Fix C1: `Bases::skip()` (which throws `SkipHandlerException`) inside a
    // handler must cause the observer to ABANDON that handler and try the
    // next registered handler on the same observer. The previous behaviour
    // — relying on `ErrorsMiddleware` to convert the exception to
    // `UnhandledSentinel` — bubbled the sentinel up through the WHOLE
    // observer trigger, aborting the per-handler iteration. Upstream
    // (`aiogram/dispatcher/event/telegram.py:127-128`) catches the exception
    // inside the handler-loop's `try/except SkipHandler: continue`, so the
    // PHP port must mirror the same per-handler scope.
    $observer = new TelegramEventObserver('message');
    $firstRan = false;
    $secondRan = false;
    $observer->register(static function () use (&$firstRan): never {
      $firstRan = true;
      Bases::skip('not for me');
    });
    $observer->register(static function () use (&$secondRan): string {
      $secondRan = true;

      return 'second';
    });

    $result = $observer->trigger(new Chat(id: 1, type: 'private'));

    self::assertTrue($firstRan, 'First handler must have been invoked before skipping.');
    self::assertTrue($secondRan, 'Second handler must execute after the first skipped.');
    self::assertSame('second', $result, 'Observer must return the second handler\'s result.');
  }

  public function testCancelHandlerStopsDispatchWithRejectedSentinel(): void
  {
    // Counterpart to SkipHandler: `Bases::cancel()` (which throws
    // `CancelHandlerException`) inside a handler stops the entire observer
    // dispatch and collapses to `RejectedSentinel`. No subsequent handler
    // on the observer runs, and the caller can use `Router::propagateEvent`'s
    // RejectedSentinel collapse (Fix I6) to fall through to `UnhandledSentinel`.
    $observer = new TelegramEventObserver('message');
    $secondCalled = false;
    $observer->register(static function (): never {
      Bases::cancel('handled elsewhere');
    });
    $observer->register(static function () use (&$secondCalled): string {
      $secondCalled = true;

      return 'second';
    });

    $result = $observer->trigger(new Chat(id: 1, type: 'private'));

    self::assertSame(RejectedSentinel::instance(), $result);
    self::assertFalse($secondCalled, 'Cancel must stop dispatch on the same observer.');
  }

  public function testRegisterAcceptsFilterSubclassInstanceAsFilter(): void
  {
    // Phase 8 regression: `register(filters: [new Command('start')])` used
    // to crash because `FilterObject::__construct` requires a `Closure`.
    // The observer now wraps any non-Closure callable via
    // `Closure::fromCallable`, matching aiogram's
    // `aiogram.dispatcher.event.handler.FilterObject` which accepts any
    // `Callable[..., Any]`. The wrapped closure must still resolve to the
    // filter's `__invoke` at dispatch time, so feed it a real Message and
    // assert the filter accepts.
    $observer = new TelegramEventObserver('message');
    $callback = static fn(): string => 'ok';
    $filter = new Command('start');

    $observer->register($callback, [$filter]);

    self::assertCount(1, $observer->handlers);
    self::assertCount(1, $observer->handlers[0]->filters);
    self::assertInstanceOf(FilterObject::class, $observer->handlers[0]->filters[0]);
    self::assertInstanceOf(Closure::class, $observer->handlers[0]->filters[0]->callback);

    // Dispatch /start through the observer and verify the wrapped filter
    // actually delegates to Command::__invoke (returns the parsed CommandObject
    // as a kwarg) — proving the Closure::fromCallable wrap preserves semantics.
    $message = new Message(
      messageId: 1,
      date: new DateTime('2024-01-01'),
      chat: new Chat(id: 1, type: 'private'),
      text: '/start',
    );
    $result = $observer->trigger($message);
    self::assertSame('ok', $result);
  }

  public function testRegisterAcceptsMagicFilterAsFilterResult(): void
  {
    // `F->...->asFilter()` returns a `MagicFilterAsFilter` (invokable Filter
    // subclass). Phase 8 must accept it directly in `filters:`. The chain
    // is `F->event->text->equals('hi')` so the MagicFilter resolves the
    // dispatch data dict (event=Message, kwargs spread). Confirm the
    // wrapping accepts it and dispatch produces the handler's return value.
    $observer = new TelegramEventObserver('message');
    $callback = static fn(): string => 'ok';

    /** @var Filter $filter */
    $filter = MagicFilter::root()->text->equals('hi')->asFilter();

    $observer->register($callback, [$filter]);

    $message = new Message(
      messageId: 1,
      date: new DateTime('2024-01-01'),
      chat: new Chat(id: 1, type: 'private'),
      text: 'hi',
    );
    self::assertSame('ok', $observer->trigger($message));
  }

  public function testRegisterAcceptsAnonymousInvokableObjectAsFilter(): void
  {
    // Plain invokable class instance (not a Filter subclass). The
    // `Closure::fromCallable` wrap should accept it as a generic callable.
    $observer = new TelegramEventObserver('message');
    $callback = static fn(): string => 'ok';
    $alwaysAccept = new class {
      public function __invoke(object $event): bool
      {
        return true;
      }
    };

    $observer->register($callback, [$alwaysAccept]);

    self::assertCount(1, $observer->handlers[0]->filters);
    $message = new Message(
      messageId: 1,
      date: new DateTime('2024-01-01'),
      chat: new Chat(id: 1, type: 'private'),
    );
    self::assertSame('ok', $observer->trigger($message));
  }

  private static function passthroughMiddleware(): BaseMiddleware
  {
    return new class extends BaseMiddleware {
      public function __invoke(Closure $handler, object $event, array $data): mixed
      {
        return $handler($event, $data);
      }
    };
  }
}
