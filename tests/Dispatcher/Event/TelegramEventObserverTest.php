<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Dispatcher\Event;

use Closure;
use Gruven\PhpBotGram\Dispatcher\Event\FilterObject;
use Gruven\PhpBotGram\Dispatcher\Event\HandlerObject;
use Gruven\PhpBotGram\Dispatcher\Event\RejectedSentinel;
use Gruven\PhpBotGram\Dispatcher\Event\TelegramEventObserver;
use Gruven\PhpBotGram\Dispatcher\Event\UnhandledSentinel;
use Gruven\PhpBotGram\Dispatcher\Flags\Flag;
use Gruven\PhpBotGram\Dispatcher\Flags\FlagDecorator;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\TelegramObject;
use PHPUnit\Framework\TestCase;

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
}
