<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm;

use Closure;
use Gruven\PhpBotGram\Dispatcher\Middlewares\EventContext;
use Gruven\PhpBotGram\Dispatcher\Middlewares\UserContextMiddleware;
use Gruven\PhpBotGram\Fsm\FsmContext;
use Gruven\PhpBotGram\Fsm\FsmContextMiddleware;
use Gruven\PhpBotGram\Fsm\FsmStrategy;
use Gruven\PhpBotGram\Fsm\Storage\BaseEventIsolation;
use Gruven\PhpBotGram\Fsm\Storage\BaseStorage;
use Gruven\PhpBotGram\Fsm\Storage\DisabledEventIsolation;
use Gruven\PhpBotGram\Fsm\Storage\Lock;
use Gruven\PhpBotGram\Fsm\Storage\MemoryStorage;
use Gruven\PhpBotGram\Fsm\Storage\SimpleEventIsolation;
use Gruven\PhpBotGram\Fsm\Storage\StorageKey;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\User;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Upstream `tests/test_fsm/test_middleware.py` cases deliberately not ported:
 *
 * - No deliberate skips. All five upstream `TestFSMContextMiddleware` test
 *   cases are covered by the methods in this file.
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 */
final class FsmContextMiddlewareTest extends TestCase
{
  // Bot id encoded in '42:TEST' token
  private const int BOT_ID = 42;
  private const int CHAT_ID = -1001234567890;
  private const int USER_ID = 7;
  private const int THREAD_ID = 99;

  private MockedBot $bot;
  private MemoryStorage $storage;
  private DisabledEventIsolation $isolation;

  protected function setUp(): void
  {
    $this->bot = new MockedBot('42:TEST');
    $this->storage = new MemoryStorage();
    $this->isolation = new DisabledEventIsolation();
  }

  // ------------------------------------------------------------------ //
  // Helpers
  // ------------------------------------------------------------------ //

  /**
   * Build an FsmContextMiddleware with the given strategy (defaults to UserInChat).
   */
  private function makeMiddleware(FsmStrategy $strategy = FsmStrategy::UserInChat): FsmContextMiddleware
  {
    return new FsmContextMiddleware(
      storage: $this->storage,
      eventsIsolation: $this->isolation,
      strategy: $strategy,
    );
  }

  /**
   * Build a `$data` bag with a pre-populated EventContext (as UserContextMiddleware would inject).
   *
   * @return array<string, mixed>
   */
  private function makeData(?int $chatId = null, ?int $userId = null, ?int $threadId = null): array
  {
    $chat = $chatId !== null ? new Chat(id: $chatId, type: 'private') : null;
    $user = $userId !== null ? new User(id: $userId, isBot: false, firstName: 'Test') : null;
    $ctx = new EventContext(chat: $chat, user: $user, threadId: $threadId);

    return [
      'bot' => $this->bot,
      UserContextMiddleware::EVENT_CONTEXT_KEY => $ctx,
    ];
  }

  /**
   * Returns a handler that captures its `$data` argument.
   *
   * @param null|array<string, mixed> $captured Reference populated inside handler.
   *
   * @return Closure(object, array<string, mixed>): mixed
   */
  private function capturingHandler(?array &$captured): Closure
  {
    return static function (object $event, array $data) use (&$captured): string {
      $captured = $data;

      return 'handled';
    };
  }

  // ------------------------------------------------------------------ //
  // Test 1: Constructor stores params and constants
  // ------------------------------------------------------------------ //

  /**
   * The constructor stores `$storage`, `$eventsIsolation`, and `$strategy`.
   * Constants have the correct string values.
   */
  public function testConstructorAndConstantValues(): void
  {
    // Verify wire-key constants match upstream naming.
    self::assertSame('state', FsmContextMiddleware::STATE_KEY);
    self::assertSame('raw_state', FsmContextMiddleware::RAW_STATE_KEY);
    self::assertSame('fsm_storage', FsmContextMiddleware::FSM_STORAGE_KEY);

    // resolveEventContext is accessible and uses the injected storage.
    $mw = $this->makeMiddleware();
    $context = $mw->resolveEventContext($this->bot, $this->makeData(self::CHAT_ID, self::USER_ID));
    self::assertInstanceOf(FsmContext::class, $context);
    self::assertSame($this->storage, $context->storage);
  }

  // ------------------------------------------------------------------ //
  // Test 2: __invoke with valid EventContext injects all three keys
  // ------------------------------------------------------------------ //

  /**
   * `__invoke` injects `fsm_storage`, `state`, and `raw_state` into `$data`
   * before delegating to the handler when a valid EventContext is present.
   */
  public function testInvokeInjectsAllThreeKeysWithValidContext(): void
  {
    $mw = $this->makeMiddleware();
    $data = $this->makeData(chatId: self::CHAT_ID, userId: self::USER_ID);
    $event = new stdClass();

    $captured = null;
    $result = $mw($this->capturingHandler($captured), $event, $data);

    self::assertSame('handled', $result);
    self::assertNotNull($captured);

    // fsm_storage must always be present.
    self::assertArrayHasKey(FsmContextMiddleware::FSM_STORAGE_KEY, $captured);
    self::assertSame($this->storage, $captured[FsmContextMiddleware::FSM_STORAGE_KEY]);

    // state key holds an FsmContext.
    self::assertArrayHasKey(FsmContextMiddleware::STATE_KEY, $captured);
    self::assertInstanceOf(FsmContext::class, $captured[FsmContextMiddleware::STATE_KEY]);

    // raw_state is null since no state has been set.
    self::assertArrayHasKey(FsmContextMiddleware::RAW_STATE_KEY, $captured);
    self::assertNull($captured[FsmContextMiddleware::RAW_STATE_KEY]);
  }

  // ------------------------------------------------------------------ //
  // Test 3: __invoke with null chatId + userId skips state injection
  // ------------------------------------------------------------------ //

  /**
   * When both `chatId` and `userId` are null, `fsm_storage` is still injected
   * but `state` and `raw_state` are NOT added to `$data`.
   */
  public function testInvokeWithNullChatAndUserSkipsStateButInjectsStorage(): void
  {
    $mw = $this->makeMiddleware();
    $data = $this->makeData(); // no chatId, no userId
    $event = new stdClass();

    $captured = null;
    $mw($this->capturingHandler($captured), $event, $data);

    self::assertNotNull($captured);

    // fsm_storage always injected.
    self::assertArrayHasKey(FsmContextMiddleware::FSM_STORAGE_KEY, $captured);
    self::assertSame($this->storage, $captured[FsmContextMiddleware::FSM_STORAGE_KEY]);

    // state and raw_state NOT present when context is null.
    self::assertArrayNotHasKey(FsmContextMiddleware::STATE_KEY, $captured);
    self::assertArrayNotHasKey(FsmContextMiddleware::RAW_STATE_KEY, $captured);
  }

  // ------------------------------------------------------------------ //
  // Test 4: resolveContext returns null when both ids are null
  // ------------------------------------------------------------------ //

  /**
   * `resolveContext` returns `null` when both `$chatId` and `$userId` are null
   * after fallbacks.
   */
  public function testResolveContextReturnNullWhenBothIdsNull(): void
  {
    $mw = $this->makeMiddleware();
    $result = $mw->resolveContext(bot: $this->bot, chatId: null, userId: null);

    self::assertNull($result);
  }

  // ------------------------------------------------------------------ //
  // Test 5: Strategy spot-checks — Chat strategy
  // ------------------------------------------------------------------ //

  /**
   * `Chat` strategy: `chatId` is used for both chat and user slots; thread is discarded.
   *
   * Mirrors `test_resolve_context_for_channel_in_chat_strategy`.
   */
  public function testResolveContextChatStrategyUsesChatIdForBothSlots(): void
  {
    $mw = $this->makeMiddleware(FsmStrategy::Chat);
    $context = $mw->resolveContext(bot: $this->bot, chatId: self::CHAT_ID, userId: self::USER_ID);

    self::assertNotNull($context);
    self::assertSame(self::CHAT_ID, $context->key->chatId);
    self::assertSame(self::CHAT_ID, $context->key->userId, 'Chat strategy must mirror chatId into userId');
    self::assertNull($context->key->threadId);
    self::assertSame(self::BOT_ID, $context->key->botId);
  }

  // ------------------------------------------------------------------ //
  // Test 6: Strategy spot-check — GlobalUser strategy
  // ------------------------------------------------------------------ //

  /**
   * `GlobalUser` strategy: `userId` is used for both chat and user slots; thread discarded.
   */
  public function testResolveContextGlobalUserStrategyUsesUserIdForBothSlots(): void
  {
    $mw = $this->makeMiddleware(FsmStrategy::GlobalUser);
    $context = $mw->resolveContext(bot: $this->bot, chatId: self::CHAT_ID, userId: self::USER_ID);

    self::assertNotNull($context);
    self::assertSame(self::USER_ID, $context->key->chatId, 'GlobalUser must set chatId = userId');
    self::assertSame(self::USER_ID, $context->key->userId);
    self::assertNull($context->key->threadId);
  }

  // ------------------------------------------------------------------ //
  // Test 7: Strategy spot-check — UserInTopic strategy
  // ------------------------------------------------------------------ //

  /**
   * `UserInTopic` strategy preserves all three values including thread ID.
   */
  public function testResolveContextUserInTopicPreservesThreadId(): void
  {
    $mw = $this->makeMiddleware(FsmStrategy::UserInTopic);
    $context = $mw->resolveContext(
      bot: $this->bot,
      chatId: self::CHAT_ID,
      userId: self::USER_ID,
      threadId: self::THREAD_ID,
    );

    self::assertNotNull($context);
    self::assertSame(self::CHAT_ID, $context->key->chatId);
    self::assertSame(self::USER_ID, $context->key->userId);
    self::assertSame(self::THREAD_ID, $context->key->threadId);
  }

  // ------------------------------------------------------------------ //
  // Test 8: Strategy spot-check — ChatTopic strategy
  // ------------------------------------------------------------------ //

  /**
   * `ChatTopic` strategy: chatId used for both slots, thread ID preserved.
   *
   * Mirrors `test_resolve_context_with_missing_user_in_chat_topic_strategy_uses_chat_id_for_user_id`.
   */
  public function testResolveContextChatTopicWithNullUserFallsBackToChatId(): void
  {
    $mw = $this->makeMiddleware(FsmStrategy::ChatTopic);
    $context = $mw->resolveContext(
      bot: $this->bot,
      chatId: self::CHAT_ID,
      userId: null,
      threadId: self::THREAD_ID,
    );

    self::assertNotNull($context, 'ChatTopic must build a context even when userId is null');
    self::assertSame(self::CHAT_ID, $context->key->chatId);
    self::assertSame(self::CHAT_ID, $context->key->userId, 'ChatTopic must fill userId = chatId when user absent');
    self::assertSame(self::THREAD_ID, $context->key->threadId);
  }

  // ------------------------------------------------------------------ //
  // Test 9: UserInChat with null userId returns null
  // ------------------------------------------------------------------ //

  /**
   * `UserInChat` strategy returns `null` when `userId` is null (channel posts have no user).
   *
   * Mirrors `test_resolve_context_for_channel_in_user_in_chat_strategy`.
   */
  public function testResolveContextUserInChatWithNullUserReturnsNull(): void
  {
    $mw = $this->makeMiddleware(FsmStrategy::UserInChat);
    $context = $mw->resolveContext(bot: $this->bot, chatId: self::CHAT_ID, userId: null);

    self::assertNull($context, 'UserInChat must return null when userId is absent');
  }

  // ------------------------------------------------------------------ //
  // Test 10: Lock-then-load order + raw_state reflects stored state
  // ------------------------------------------------------------------ //

  /**
   * The `raw_state` injected into `$data` matches the state actually stored in
   * storage at the time the lock is held. This verifies the lock-then-load
   * ordering required by the upstream bugfix.
   *
   * Uses `SimpleEventIsolation` (a real mutex) to exercise the lock path.
   */
  public function testRawStateReflectsActualStoredStateInsideLock(): void
  {
    $storage = new MemoryStorage();
    $isolation = new SimpleEventIsolation();

    $mw = new FsmContextMiddleware(
      storage: $storage,
      eventsIsolation: $isolation,
      strategy: FsmStrategy::UserInChat,
    );

    // Pre-set state in storage directly before the middleware runs.
    $key = new StorageKey(botId: self::BOT_ID, chatId: self::CHAT_ID, userId: self::USER_ID);
    $storage->setState($key, 'Form:step_one');

    $data = $this->makeData(chatId: self::CHAT_ID, userId: self::USER_ID);
    $event = new stdClass();

    $captured = null;
    $mw($this->capturingHandler($captured), $event, $data);

    self::assertNotNull($captured);
    self::assertSame('Form:step_one', $captured[FsmContextMiddleware::RAW_STATE_KEY]);
  }

  // ------------------------------------------------------------------ //
  // Test 11: close() delegates to both storage and isolation
  // ------------------------------------------------------------------ //

  /**
   * `close()` calls `close()` on both the storage and the event isolation.
   *
   * Uses spy-style tracking via anonymous classes with a shared counter object.
   */
  public function testCloseCallsBothStorageCloseAndIsolationClose(): void
  {
    // Use an object container so the anonymous classes can mutate a shared
    // flag without requiring a PHPStan-unfriendly reference-to-scalar.
    $spy = new stdClass();
    $spy->storageClosed = false;
    $spy->isolationClosed = false;

    // Spy storage
    $spyStorage = new class ($spy) extends BaseStorage {
      public function __construct(private readonly stdClass $spy) {}

      public function setState(StorageKey $key, null|object|string $state = null): void {}

      public function getState(StorageKey $key): ?string
      {
        return null;
      }

      public function setData(StorageKey $key, array $data): void {}

      /** @return array<string, mixed> */
      public function getData(StorageKey $key): array
      {
        return [];
      }

      public function close(): void
      {
        $this->spy->storageClosed = true;
      }
    };

    // Spy isolation
    $spyIsolation = new class ($spy) extends BaseEventIsolation {
      public function __construct(private readonly stdClass $spy) {}

      public function lock(StorageKey $key): Lock
      {
        return new Lock(null);
      }

      public function close(): void
      {
        $this->spy->isolationClosed = true;
      }
    };

    $mw = new FsmContextMiddleware(
      storage: $spyStorage,
      eventsIsolation: $spyIsolation,
    );

    $mw->close();

    self::assertTrue($spy->storageClosed, 'close() must call storage->close()');
    self::assertTrue($spy->isolationClosed, 'close() must call eventsIsolation->close()');
  }

  // ------------------------------------------------------------------ //
  // Test 12: chatId=null fallback uses userId for chatId slot
  // ------------------------------------------------------------------ //

  /**
   * When `$chatId` is null, upstream uses `$userId` as a substitute for
   * the chat slot (e.g. DM / user-only events like inline queries).
   *
   * The key's `chatId` and `userId` must both be `$userId` after fallback
   * when using `UserInChat` strategy (chat slot promoted from userId).
   */
  public function testResolveContextWithNullChatIdFallsBackToUserId(): void
  {
    $mw = $this->makeMiddleware(FsmStrategy::UserInChat);
    // chatId=null forces the fallback: chatId = userId.
    $context = $mw->resolveContext(bot: $this->bot, chatId: null, userId: self::USER_ID);

    self::assertNotNull($context);
    // After fallback chatId = USER_ID; strategy is UserInChat so both stay as-is.
    self::assertSame(self::USER_ID, $context->key->chatId);
    self::assertSame(self::USER_ID, $context->key->userId);
  }

  // ------------------------------------------------------------------ //
  // Test 13: getContext builds the correct StorageKey
  // ------------------------------------------------------------------ //

  /**
   * `getContext()` constructs an `FsmContext` whose `StorageKey` reflects the
   * exact ids passed in, using the bot's id for `botId`.
   */
  public function testGetContextBuildsCorrectStorageKey(): void
  {
    $mw = $this->makeMiddleware();
    $ctx = $mw->getContext(
      bot: $this->bot,
      chatId: self::CHAT_ID,
      userId: self::USER_ID,
      threadId: self::THREAD_ID,
      businessConnectionId: 'bc-1',
      destiny: 'custom',
    );

    self::assertSame(self::BOT_ID, $ctx->key->botId);
    self::assertSame(self::CHAT_ID, $ctx->key->chatId);
    self::assertSame(self::USER_ID, $ctx->key->userId);
    self::assertSame(self::THREAD_ID, $ctx->key->threadId);
    self::assertSame('bc-1', $ctx->key->businessConnectionId);
    self::assertSame('custom', $ctx->key->destiny);
    self::assertSame($this->storage, $ctx->storage);
  }
}
