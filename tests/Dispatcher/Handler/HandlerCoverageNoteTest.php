<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Dispatcher\Handler;

use Gruven\PhpBotGram\Dispatcher\Event\CallableObject;
use Gruven\PhpBotGram\Dispatcher\Event\HandlerObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use stdClass;

/**
 * Coverage note for upstream `tests/test_handler/`.
 *
 * Upstream (aiogram) ships a `BaseHandler` abstract class and per-type
 * subclasses (`MessageHandler`, `CallbackQueryHandler`, `ErrorHandler`, …).
 * Handlers are class instances that receive `event` + a `data` dict and
 * implement an abstract `handle()` coroutine. phpbotgram uses Closure-based
 * handlers exclusively — there is no `MessageHandler` hierarchy.
 *
 * **Upstream → local coverage mapping**:
 *
 * | Upstream file               | Disposition                                          |
 * |-----------------------------|------------------------------------------------------|
 * | test_base.py (64 lines)     | API divergence (a): class-based BaseHandler absent   |
 * | test_callback_query.py (24) | API divergence (a): CallbackQueryHandler absent      |
 * | test_chat_member.py (25)    | API divergence (a): ChatMemberHandler absent         |
 * | test_chosen_inline_result.py| API divergence (a): ChosenInlineResultHandler absent |
 * | test_error.py (17)          | API divergence (a): ErrorHandler absent              |
 * | test_inline_query.py (23)   | API divergence (a): InlineQueryHandler absent        |
 * | test_message.py (61)        | API divergence (a): MessageHandler absent            |
 * | test_poll.py (31)           | API divergence (a): PollHandler absent               |
 * | test_pre_checkout_query.py  | API divergence (a): PreCheckoutQueryHandler absent   |
 * | test_shipping_query.py (29) | API divergence (a): ShippingQueryHandler absent      |
 *
 * All 10 upstream files fall under skip category **(a) API divergence**:
 * phpbotgram's handler surface is Closure-based (registered via
 * `TelegramEventObserver::register(Closure, …)`), not class-based
 * (subclassing `BaseHandler` with an overridden `handle()` method).
 * The class-based Handler hierarchy is a Phase-scope deferral — it is not
 * present in `src/` and is out of scope for the current port.
 *
 * What IS covered:
 * - Equivalent behaviours tested via `HandlerObjectTest` (parameter
 *   injection, filter pipeline, flags storage) and `TelegramEventObserverTest`
 *   (registration, trigger, middleware composition).
 * - `test_base.py::test_wrapped_handler` (HandlerObject.awaitable) →
 *   see `testCallableObjectHasNoBoolAwaitableFlag` below; phpbotgram
 *   does not carry an `awaitable` flag because the runtime is always
 *   synchronous from the caller's perspective.
 *
 * @internal
 */
final class HandlerCoverageNoteTest extends TestCase
{
  /**
   * Upstream `test_base.py::test_wrapped_handler` checks that `HandlerObject`
   * exposes an `awaitable` boolean. phpbotgram's `CallableObject` intentionally
   * omits this flag — Fibers handle I/O non-blocking; from the handler
   * registration surface everything is synchronous.
   *
   * This test verifies the divergence is intentional: `HandlerObject`
   * IS a `CallableObject`, and `CallableObject` deliberately has no
   * `awaitable` property. The `callback` and `params()` inspection
   * surface is the same shape, which is what production code depends on.
   */
  public function testHandlerObjectIsCallableObjectWithNoAwaitableProperty(): void
  {
    // API divergence (a): upstream HandlerObject has an `awaitable` flag;
    // phpbotgram omits it (see CallableObject docblock). Verify the
    // invariant is intentional — no surprise regression if someone adds
    // the property back.
    $callback = static fn(): bool => true;
    $handler = new HandlerObject($callback);

    self::assertInstanceOf(CallableObject::class, $handler);
    // PHPStan correctly identifies that HandlerObject has no 'awaitable' property.
    // The assertion is intentional documentation of the API divergence — we
    // verify the reflection-level absence matches the design intent.
    $properties = array_map(
      static fn(ReflectionProperty $p): string => $p->getName(),
      (new ReflectionClass($handler))->getProperties(),
    );
    self::assertNotContains(
      'awaitable',
      $properties,
      'phpbotgram intentionally omits the awaitable flag (API divergence from upstream BaseHandler).',
    );
    self::assertSame($callback, $handler->callback);
  }

  /**
   * Upstream `test_base.py::test_base_handler` verifies the `event` and `data`
   * properties of a constructed `BaseHandler`. phpbotgram's equivalent contract
   * is that `HandlerObject` stores the callback and exposes `params()` for
   * kwarg filtering — the "event" object is passed as a positional arg to
   * `call()`, not stored on the handler.
   *
   * Port rationale: `HandlerObject::call(['event'], ['key' => 42])` is
   * semantically equivalent to `MyHandler(event=event, key=42).handle()` —
   * the event is positional, keyword args thread through `params()` filtering.
   */
  public function testHandlerObjectCallPassesEventPositionallyAndKwargsFiltered(): void
  {
    // Equivalent to upstream test_base.py::test_base_handler:
    // handler receives the event positionally + named kwargs.
    $receivedEvent = null;
    $receivedKey = null;
    $handler = new HandlerObject(static function (
      object $event,
      int $key,
    ) use (&$receivedEvent, &$receivedKey): int {
      $receivedEvent = $event;
      $receivedKey = $key;

      return 42;
    });

    $fakeEvent = new stdClass();
    $result = $handler->call([$fakeEvent], ['key' => 42, 'extra_ignored' => 99]);

    self::assertSame(42, $result);
    self::assertSame($fakeEvent, $receivedEvent);
    self::assertSame(42, $receivedKey);
  }

  /**
   * Upstream `test_base.py::test_update_from_data` checks that
   * `handler.update` resolves to the `Update` passed in the data dict.
   * phpbotgram's equivalent: the `Update` is injected as a kwarg named
   * `event_update` (see DispatcherTest). This test verifies the parameter
   * name reaches the handler correctly.
   */
  public function testHandlerReceivesUpdateViaEventUpdateKwarg(): void
  {
    // Ported from test_base.py::test_update_from_data (adapted for Closure API).
    // In the real dispatch chain, Dispatcher::feedUpdate injects `event_update`
    // into the kwargs bag. Here we simulate that directly.
    $observed = null;
    $handler = new HandlerObject(static function (
      object $event_update,
    ) use (&$observed): void {
      $observed = $event_update;
    });

    $update = new stdClass();
    $handler->call([], ['event_update' => $update, 'other' => 'ignored']);

    self::assertSame($update, $observed);
  }

  /**
   * Upstream `test_base.py::test_bot_from_context_missing` raises
   * `RuntimeError` when `handler.bot` is accessed without a bot in the data.
   * phpbotgram surfaces this as simply not having the `$bot` parameter
   * satisfied — if the handler declares `Bot $bot` but no `bot:` kwarg is
   * injected, PHP raises a `TypeError` on the call. This test verifies
   * the param-filtering contract that PREVENTS accidental injection of
   * unrelated kwargs.
   */
  public function testHandlerWithNoBotKwargLeavesParamUnsatisfied(): void
  {
    // API divergence (a): upstream's bot-context-missing raises RuntimeError
    // from the handler instance. phpbotgram doesn't store context on the
    // handler — it relies on kwarg injection. When `bot:` is absent from
    // kwargs and the handler declares `$bot`, the call will TypeError.
    // We test the filtering surface: params() exposes 'bot' and
    // prepareKwargs returns an empty array if 'bot' wasn't injected.
    $handler = new HandlerObject(static function (stdClass $bot): void {});

    $filtered = $handler->prepareKwargs([]);

    self::assertSame([], $filtered, 'prepareKwargs must not fabricate absent kwargs.');
    self::assertContains('bot', $handler->params());
  }
}
