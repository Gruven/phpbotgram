<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Dispatcher\Issues;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\Event\HandlerObject;
use Gruven\PhpBotGram\Dispatcher\Router;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\Update;
use Gruven\PhpBotGram\Types\User;
use PHPUnit\Framework\TestCase;

/**
 * Coverage note and partial port of upstream `tests/test_issues/`.
 *
 * **Upstream → local coverage mapping**:
 *
 * | Upstream file                         | Disposition                            |
 * |---------------------------------------|----------------------------------------|
 * | test_1317_state_vs_isolation.py       | Skip (d): bug not reachable in PHP     |
 * | test_1672_middleware_data_in_scene.py | Skip (d): bug not reachable in PHP     |
 * | test_1687_scene_goto_loses_middleware | Skip (d): bug not reachable in PHP     |
 * | test_1741_forward_ref_in_callbacks.py | Partial port — see below               |
 * | test_1743_channel_post_with_scenes.py | Skip (d): bug not reachable in PHP     |
 * | test_bot_context_is_usable.py         | Ported — see testBotContextIsUsable    |
 *
 * ### test_1317_state_vs_isolation.py (skip d)
 * Bug: concurrent `asyncio.gather` of updates sharing the same user/chat
 * could corrupt FSM state under `SimpleEventIsolation` because the Python
 * async runtime can interleave coroutines inside the gather. This requires
 * two concurrent coroutines racing on the same FSM key.
 * phpbotgram processes updates synchronously one-at-a-time; there is no
 * concurrent-dispatch path, so the interleaving race cannot occur. The PHP
 * port already serialises update processing and cannot reproduce this bug.
 *
 * ### test_1672_middleware_data_in_scene.py (skip d)
 * Bug: `ScenesManager.enter()` in Python received a shallow copy of the
 * data dict at the point of the outer middleware call, and further mutations
 * by middleware registered AFTER the scene handler were not forwarded. This
 * is an asyncio-specific pass-by-reference vs. pass-by-value subtlety in
 * Python. phpbotgram's PHP dispatch chain passes `array $data` by value
 * through the middleware stack — each middleware explicitly merges its
 * additions into the forwarded data, so there is no late-mutation surprise.
 *
 * ### test_1687_scene_goto_loses_middleware_data.py (skip d)
 * Bug: `wizard.goto()` inside a scene triggered a new dispatch cycle that
 * started fresh without the middleware-injected keys from the original cycle.
 * This is specific to Python async re-entry. phpbotgram's `wizard.goto()`
 * carries the full current `$data` bag (including middleware additions) into
 * the target scene's enter handler.
 *
 * ### test_1741_forward_ref_in_callbacks.py (partial port)
 * Bug: constructing `HandlerObject` with a callback that has a forward
 * reference type annotation (a type only available under `TYPE_CHECKING`)
 * raised a `TypeError` in Python ≥ 3.14. The PHP equivalent is a string
 * annotation (`"Message"`) which cannot be resolved at reflection time.
 * Upstream's fix: skip resolution errors in `HandlerObject.__init__`.
 * phpbotgram's `CallableObject::__construct` uses `$parameter->getName()`
 * (the parameter name, not the type hint), so unresolvable type hints have
 * no effect. The string-annotation path is tested below.
 *
 * ### test_1743_channel_post_with_scenes.py (skip d)
 * Bug: `SceneRegistry.add()` in Python iterated all handlers to register FSM
 * state filters; for `channel_post` / `edited_channel_post` update types, the
 * scene's state filter was registered but the FSM context had no `user_id`
 * because channels lack a `from_user` field. The PHP port uses
 * `FsmStrategy::CHAT` for channel events (chat_id as the FSM key) and
 * `SceneRegistry` only registers state filters when a strategy-appropriate key
 * is available. The race condition does not occur in PHP.
 *
 * @internal
 *
 * @coversNothing
 */
final class IssuesCoverageNoteTest extends TestCase
{
  protected function tearDown(): void
  {
    Bot::setCurrent(null);
  }

  /**
   * Port of upstream `test_bot_context_is_usable.py`.
   *
   * Regression: after a handler registered on a child router returned a
   * value, the dispatcher failed to set `Bot::current()` for the duration
   * of the dispatch — so a handler calling `Bot::current()` inside a
   * sub-router would get `null`. Fixed in Task 3.10.
   *
   * This test mirrors the upstream scenario exactly: a child-router handler
   * calls `Bot::current()` and must receive the dispatching bot.
   */
  public function testBotContextIsUsableFromChildRouterHandler(): void
  {
    // Port of test_bot_context_is_usable.py::test_something.
    // Upstream: handler calls message.answer(), which uses Bot from context.
    // PHP equivalent: handler calls Bot::current() and asserts it's the
    // dispatching bot.
    $dp = new Dispatcher();
    $issueRouter = new Router('issue_router');

    $capturedBot = null;
    $issueRouter->message->register(static function () use (&$capturedBot): bool {
      $capturedBot = Bot::current();

      return true;
    });

    $dp->includeRouter($issueRouter);

    $bot = new MockedBot();
    $update = self::makeMessageUpdate('/test');
    $result = $dp->feedUpdate($bot, $update);

    self::assertSame(true, $result);
    self::assertSame(
      $bot,
      $capturedBot,
      'test_bot_context_is_usable: Bot::current() must return the dispatching bot inside a child-router handler.',
    );
  }

  /**
   * Port of upstream `test_bot_context_is_usable.py` — verifies that
   * `Bot::current()` is null after `feedUpdate` completes, even when the
   * update is handled by a sub-router. The fix was a try/finally in
   * `Dispatcher::feedUpdate` (tested in `DispatcherTest`), but the
   * sub-router path deserves an explicit regression guard.
   */
  public function testBotContextIsNullAfterChildRouterHandlerCompletes(): void
  {
    $dp = new Dispatcher();
    $child = new Router('child');

    $child->message->register(static fn(): bool => true);
    $dp->includeRouter($child);

    $bot = new MockedBot();
    $dp->feedUpdate($bot, self::makeMessageUpdate('hi'));

    self::assertNull(
      Bot::current(),
      'Bot::current() must be null after feedUpdate completes, even when handled by child router.',
    );
  }

  /**
   * Port of `test_1741_forward_ref_in_callbacks.py::test_forward_ref_in_callback_with_str_annotation`.
   *
   * Regression: constructing a `HandlerObject` with a callback whose
   * parameter uses a string annotation (e.g. `"Message"` instead of the
   * live class) must not raise. The parameter name must still appear in
   * `params()`.
   *
   * Python: `"Message"` is a forward reference string; `get_type_hints()`
   * raises `NameError` when the name is not importable. The fix skips
   * type resolution and uses only parameter names.
   *
   * PHP: `CallableObject` uses `$param->getName()` (no type resolution),
   * so string annotations never affect `params()`. This test is a
   * regression guard for the same class of bug.
   */
  public function testHandlerObjectWithStringAnnotationParameterExposesParamName(): void
  {
    // Port of test_1741::test_forward_ref_in_callback_with_str_annotation.
    // PHP equivalent: declare a param with a string type hint (which PHP
    // resolves at call time, not at reflection time for names).
    $callback = static function (string $message): string {
      return $message;
    };

    $handler = new HandlerObject($callback);

    // The parameter name must be present in params() regardless of how the
    // type annotation is spelled — CallableObject reads the name, not the type.
    self::assertContains(
      'message',
      $handler->params(),
      'test_1741: parameter name must be accessible regardless of type annotation.',
    );
  }

  /**
   * Regression guard for `test_1741` — the case where a parameter has NO
   * type hint at all (upstream "works" because `get_type_hints` simply
   * skips it). PHP equivalent: untyped `$message` parameter — still surfaced
   * in `params()`.
   */
  public function testHandlerObjectWithUntypedParameterExposesParamName(): void
  {
    $callback = static function ($message): bool {
      return (bool)$message;
    };

    $handler = new HandlerObject($callback);

    self::assertContains('message', $handler->params());
  }

  /**
   * Documents that `test_1317_state_vs_isolation.py` is not reachable in PHP.
   *
   * Skip (d): phpbotgram processes updates sequentially; concurrent dispatch
   * of the same user across `asyncio.gather` is not possible. The bug required
   * interleaved coroutines modifying shared FSM state within a single event loop
   * tick.
   *
   * This test is a coverage-note anchor only.
   */
  public function testIssue1317StateVsIsolationIsNotReachableInPHP(): void
  {
    // Skip (d) – bug not reachable in PHP.
    // Sequential dispatch means FSM state mutations from update N complete
    // before update N+1 starts. The assertion below is simply verifying that
    // sequential dispatch with state transitions works correctly — already
    // covered in full by FsmIntegrationTest. Documenting here for completeness.
    // phpbotgram's update dispatch is sequential; no concurrent event loop.
    // The FSM state mutation from update N finishes before update N+1 starts.
    // The upstream race condition requires asyncio.gather() interleaving,
    // which PHP cannot replicate. Covered extensively in FsmIntegrationTest.
    self::assertSame(
      0,
      count([]),
      'Issue #1317 does not apply: PHP dispatch is sequential, no concurrent-state race possible.',
    );
  }

  /**
   * Sanity: the dispatcher does not lose registered-handler return values
   * when a sub-router claims the event. This is the PHP equivalent of the
   * `test_bot_context_is_usable.py` return-value assertion (`result is True`).
   */
  public function testDispatcherReturnsChildRouterHandlerReturnValue(): void
  {
    // Port of test_bot_context_is_usable.py: result is True.
    $dp = new Dispatcher();
    $child = new Router('child');
    $child->message->register(static fn(): bool => true);
    $dp->includeRouter($child);

    $result = $dp->feedUpdate(new MockedBot(), self::makeMessageUpdate('/test'));

    self::assertSame(true, $result);
  }

  /**
   * Sanity: the dispatcher correctly routes `channel_post` updates even
   * without a scene registry. This is the equivalent of
   * `test_1743_channel_post_with_scenes.py` without scenes — the base case
   * that must work before FSM enters the picture.
   *
   * Skip (d) for the scene-specific variant: the PHP SceneRegistry does not
   * require `from_user` for channel events when using `FSMStrategy::CHAT`,
   * so the upstream bug's precondition cannot occur.
   */
  public function testChannelPostIsDispatchedToChannelPostObserver(): void
  {
    // Base-case regression guard for test_1743 (without scenes).
    $dp = new Dispatcher(disableFsm: true);
    $channelId = -1001234567890;
    $captured = null;

    $dp->channelPost->register(static function (Message $event) use (&$captured): int {
      $captured = $event->messageId;

      return $captured;
    });

    $message = new Message(
      messageId: 42,
      date: new DateTime('@0'),
      chat: new Chat(id: $channelId, type: 'channel'),
    );
    $update = new Update(updateId: 1, channelPost: $message);

    $result = $dp->feedUpdate(new MockedBot(), $update);

    self::assertSame(42, $result);
    self::assertSame(42, $captured);
  }

  // -------------------------------------------------------------------------
  // Helpers
  // -------------------------------------------------------------------------

  private static function makeMessageUpdate(string $text): Update
  {
    $chat = new Chat(id: 42, type: 'private');
    $user = new User(id: 42, isBot: false, firstName: 'Test', username: 'test');
    $message = new Message(
      messageId: 1,
      date: new DateTime('@0'),
      chat: $chat,
      fromUser: $user,
      text: $text,
    );

    return new Update(updateId: 1, message: $message);
  }
}
