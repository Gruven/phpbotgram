<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm;

use Closure;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\Event\UnhandledSentinel;
use Gruven\PhpBotGram\Filters\StateFilter;
use Gruven\PhpBotGram\Fsm\FsmContextMiddleware;
use Gruven\PhpBotGram\Fsm\FsmStrategy;
use Gruven\PhpBotGram\Fsm\State;
use Gruven\PhpBotGram\Fsm\StatesGroup;
use Gruven\PhpBotGram\Fsm\Storage\DisabledEventIsolation;
use Gruven\PhpBotGram\Fsm\Storage\MemoryStorage;
use Gruven\PhpBotGram\Fsm\Storage\StorageKey;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\Update;
use Gruven\PhpBotGram\Types\User;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests that drive the full dispatcher pipeline with
 * `FsmContextMiddleware` and assert that `StateFilter` receives the
 * `raw_state` kwarg injected by the middleware.
 *
 * Critical #1 regression guard: before the fix, `FsmContextMiddleware` wrote
 * `raw_state` (snake_case) but `StateFilter`, `State::__invoke`, and
 * `StatesGroup::match` read `rawState` (camelCase) — meaning every
 * scene-state-gated handler silently fell through even when the FSM state
 * matched.
 */

// ---------------------------------------------------------------------------
// File-scope fixture group so `CHILDREN` constant resolution works.
// ---------------------------------------------------------------------------

/**
 * Simple two-state form used by the integration tests.
 */
final class FsmIntegrationFormStates extends StatesGroup
{
  public static State $idle;
  public static State $active;
}

// ---------------------------------------------------------------------------

final class FsmIntegrationTest extends TestCase
{
  private const int BOT_ID = 42;
  private const int CHAT_ID = 100;
  private const int USER_ID = 7;

  private MockedBot $bot;
  private MemoryStorage $storage;

  protected function setUp(): void
  {
    $this->bot = new MockedBot('42:TEST');
    $this->storage = new MemoryStorage();
    FsmIntegrationFormStates::bootstrapIfNeeded();
  }

  protected function tearDown(): void
  {
    Bot::setCurrent(null);
  }

  // ------------------------------------------------------------------ //
  // Helpers
  // ------------------------------------------------------------------ //

  /**
   * Build a minimal Update carrying a Message from the canonical test user/chat.
   */
  private function makeUpdate(string $text = 'hello'): Update
  {
    $chat = new Chat(id: self::CHAT_ID, type: 'private');
    $user = new User(id: self::USER_ID, isBot: false, firstName: 'Tester');
    $message = new Message(
      messageId: 1,
      date: new DateTime('@0'),
      chat: $chat,
      fromUser: $user,
      text: $text,
    );

    return new Update(updateId: 1, message: $message);
  }

  /**
   * Build a Dispatcher with `FsmContextMiddleware` wired as an outer
   * middleware on the `message` observer.
   *
   * The dispatcher-level chain (UserContextMiddleware → ErrorsMiddleware)
   * runs first (injecting `event_context`), and the per-observer outer
   * middleware runs inside `propagateEvent`, so `FsmContextMiddleware`
   * sees the fully populated `event_context` from `UserContextMiddleware`.
   */
  private function makeDispatcher(): Dispatcher
  {
    $dispatcher = new Dispatcher();

    $fsmMiddleware = new FsmContextMiddleware(
      storage: $this->storage,
      eventsIsolation: new DisabledEventIsolation(),
      strategy: FsmStrategy::UserInChat,
    );

    $dispatcher->message->outerMiddleware($fsmMiddleware);

    return $dispatcher;
  }

  /**
   * Return the `StorageKey` that the dispatcher will derive for the
   * canonical test user / chat / bot combination with `UserInChat` strategy.
   */
  private function storageKey(): StorageKey
  {
    return new StorageKey(
      botId: self::BOT_ID,
      chatId: self::CHAT_ID,
      userId: self::USER_ID,
    );
  }

  // ------------------------------------------------------------------ //
  // Critical #1: raw_state kwarg flows from middleware to StateFilter
  // ------------------------------------------------------------------ //

  /**
   * A handler gated by `StateFilter('Form:idle')` must fire when the
   * dispatcher's FSM middleware injects `raw_state = 'Form:idle'`.
   *
   * This is the Critical #1 integration gap: before the fix, the middleware
   * wrote `raw_state` (snake_case) but `StateFilter` read `rawState`
   * (camelCase), so the filter always saw `null` and rejected every
   * state-gated handler.
   */
  public function testStatFilterMatchesWhenFsmMiddlewareInjectsMatchingRawState(): void
  {
    $dispatcher = $this->makeDispatcher();

    // Pre-set the FSM state so the middleware will load 'Form:idle'.
    $this->storage->setState($this->storageKey(), 'Form:idle');

    $handlerFired = false;
    $dispatcher->message->register(
      static function () use (&$handlerFired): string {
        $handlerFired = true;

        return 'handled';
      },
      [Closure::fromCallable(new StateFilter('Form:idle'))],
    );

    $result = $dispatcher->feedUpdate($this->bot, $this->makeUpdate());

    self::assertSame('handled', $result, 'Handler must fire when state matches.');
    self::assertTrue($handlerFired, 'Handler closure must have been called.');
  }

  /**
   * A handler gated by `StateFilter('Form:idle')` must NOT fire when the
   * stored FSM state is `'Form:active'` — wrong state, filter must reject.
   */
  public function testStateFilterDoesNotMatchWhenRawStateDiffers(): void
  {
    $dispatcher = $this->makeDispatcher();

    $this->storage->setState($this->storageKey(), 'Form:active');

    $handlerFired = false;
    $dispatcher->message->register(
      static function () use (&$handlerFired): string {
        $handlerFired = true;

        return 'wrong';
      },
      [Closure::fromCallable(new StateFilter('Form:idle'))],
    );

    $result = $dispatcher->feedUpdate($this->bot, $this->makeUpdate());

    self::assertSame(UnhandledSentinel::instance(), $result, 'Handler must NOT fire when state does not match.');
    self::assertFalse($handlerFired, 'Handler closure must NOT have been called.');
  }

  /**
   * A handler gated by a `State` instance fires when the stored FSM state
   * matches the state's qualified name.
   *
   * This exercises the `State::__invoke` path that reads `raw_state` from
   * the kwargs bag (the second consumer fixed by Critical #1).
   */
  public function testStateInstanceFilterMatchesViaInvokeWithRawStateKwarg(): void
  {
    FsmIntegrationFormStates::bootstrapIfNeeded();
    $dispatcher = $this->makeDispatcher();

    // FsmIntegrationFormStates::$idle resolves to 'FsmIntegrationFormStates:idle'.
    $qualifiedState = FsmIntegrationFormStates::$idle->state();
    self::assertNotNull($qualifiedState);

    $this->storage->setState($this->storageKey(), $qualifiedState);

    $handlerFired = false;
    $dispatcher->message->register(
      static function () use (&$handlerFired): string {
        $handlerFired = true;

        return 'state-instance-match';
      },
      [Closure::fromCallable(new StateFilter(FsmIntegrationFormStates::$idle))],
    );

    $result = $dispatcher->feedUpdate($this->bot, $this->makeUpdate());

    self::assertSame('state-instance-match', $result);
    self::assertTrue($handlerFired);
  }

  /**
   * A handler gated by a `StatesGroup` instance fires when the stored FSM
   * state is one of the group's state names.
   *
   * This exercises the `StatesGroup::match` path that reads `raw_state`
   * (the third consumer fixed by Critical #1).
   */
  public function testStatesGroupFilterMatchesViaMatchWithRawStateKwarg(): void
  {
    FsmIntegrationFormStates::bootstrapIfNeeded();
    $dispatcher = $this->makeDispatcher();

    $activeState = FsmIntegrationFormStates::$active->state();
    self::assertNotNull($activeState);

    $this->storage->setState($this->storageKey(), $activeState);

    $handlerFired = false;
    $dispatcher->message->register(
      static function () use (&$handlerFired): string {
        $handlerFired = true;

        return 'group-match';
      },
      [Closure::fromCallable(new StateFilter(FsmIntegrationFormStates::class))],
    );

    $result = $dispatcher->feedUpdate($this->bot, $this->makeUpdate());

    self::assertSame('group-match', $result);
    self::assertTrue($handlerFired);
  }

  /**
   * The `raw_state` value injected by `FsmContextMiddleware` is accessible
   * inside a handler via the `array $data` bag — verifying the pipeline
   * end-to-end, including the constant name `FsmContextMiddleware::RAW_STATE_KEY`.
   */
  public function testRawStateKeyIsInjectedIntoHandlerData(): void
  {
    $dispatcher = $this->makeDispatcher();

    $this->storage->setState($this->storageKey(), 'Form:step_one');

    $capturedRawState = null;
    $dispatcher->message->register(
      // CallableObject binds named params by variable name. Since PHP
      // identifiers accept underscores, `$raw_state` matches the
      // `raw_state` key that `FsmContextMiddleware` injects.
      static function (mixed $raw_state = null) use (&$capturedRawState): string {
        $capturedRawState = $raw_state;

        return 'captured';
      },
    );

    $dispatcher->feedUpdate($this->bot, $this->makeUpdate());

    self::assertSame(
      'Form:step_one',
      $capturedRawState,
      sprintf(
        "Handler must see \$raw_state = '%s' (key: '%s') injected by FsmContextMiddleware.",
        'Form:step_one',
        FsmContextMiddleware::RAW_STATE_KEY,
      ),
    );
  }

  /**
   * When no FSM state has been stored, `raw_state` is `null` and a
   * `StateFilter(null)` (no-state sentinel) must match.
   */
  public function testNullStateFilterMatchesWhenNoFsmStateStored(): void
  {
    $dispatcher = $this->makeDispatcher();
    // No setState call — raw_state will be null.

    $handlerFired = false;
    $dispatcher->message->register(
      static function () use (&$handlerFired): string {
        $handlerFired = true;

        return 'no-state';
      },
      [Closure::fromCallable(new StateFilter(null))],
    );

    $result = $dispatcher->feedUpdate($this->bot, $this->makeUpdate());

    self::assertSame('no-state', $result);
    self::assertTrue($handlerFired);
  }
}
