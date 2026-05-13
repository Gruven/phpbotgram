<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm;

use Gruven\PhpBotGram\Fsm\FsmStrategy;
use PHPUnit\Framework\TestCase;

/**
 * Covers `FsmStrategy` enum and its `apply()` method.
 *
 * Mirrors upstream `aiogram.fsm.strategy.FSMStrategy` and the module-level
 * `apply_strategy()` function (`aiogram/fsm/strategy.py`).
 */
final class FsmStrategyTest extends TestCase
{
  private const int CHAT_ID = 100;
  private const int USER_ID = 42;
  private const int THREAD_ID = 7;

  // ------------------------------------------------------------------ //
  // UserInChat (default)
  // ------------------------------------------------------------------ //

  /**
   * `UserInChat::apply()` returns `(chatId, userId, null)` — thread is discarded.
   */
  public function testUserInChatReturnsUserAndChatWithNullThread(): void
  {
    $result = FsmStrategy::UserInChat->apply(self::CHAT_ID, self::USER_ID);

    self::assertSame(self::CHAT_ID, $result['chatId']);
    self::assertSame(self::USER_ID, $result['userId']);
    self::assertNull($result['threadId']);
  }

  /**
   * `UserInChat::apply()` discards the thread ID even when one is provided.
   *
   * Upstream: `return chat_id, user_id, None` regardless of `thread_id`.
   */
  public function testUserInChatDiscardsThreadIdWhenProvided(): void
  {
    $result = FsmStrategy::UserInChat->apply(self::CHAT_ID, self::USER_ID, self::THREAD_ID);

    self::assertNull($result['threadId'], 'UserInChat must always return null threadId');
    self::assertSame(self::CHAT_ID, $result['chatId']);
    self::assertSame(self::USER_ID, $result['userId']);
  }

  // ------------------------------------------------------------------ //
  // Chat
  // ------------------------------------------------------------------ //

  /**
   * `Chat::apply()` returns `(chatId, chatId, null)` — userId and thread are normalised.
   *
   * Mirrors upstream: `return chat_id, chat_id, None`.
   */
  public function testChatReturnsSharedChatKeyWithNullThread(): void
  {
    $result = FsmStrategy::Chat->apply(self::CHAT_ID, self::USER_ID, self::THREAD_ID);

    self::assertSame(self::CHAT_ID, $result['chatId']);
    self::assertSame(self::CHAT_ID, $result['userId'], 'Chat strategy must set userId = chatId');
    self::assertNull($result['threadId']);
  }

  // ------------------------------------------------------------------ //
  // GlobalUser
  // ------------------------------------------------------------------ //

  /**
   * `GlobalUser::apply()` returns `(userId, userId, null)` — chatId and thread are normalised.
   *
   * Mirrors upstream: `return user_id, user_id, None`.
   */
  public function testGlobalUserReturnsSharedUserKeyWithNullThread(): void
  {
    $result = FsmStrategy::GlobalUser->apply(self::CHAT_ID, self::USER_ID, self::THREAD_ID);

    self::assertSame(self::USER_ID, $result['chatId'], 'GlobalUser strategy must set chatId = userId');
    self::assertSame(self::USER_ID, $result['userId']);
    self::assertNull($result['threadId']);
  }

  // ------------------------------------------------------------------ //
  // UserInTopic
  // ------------------------------------------------------------------ //

  /**
   * `UserInTopic::apply()` preserves all three values including thread ID.
   *
   * Mirrors upstream: `return chat_id, user_id, thread_id`.
   */
  public function testUserInTopicPreservesAllThreeValues(): void
  {
    $result = FsmStrategy::UserInTopic->apply(self::CHAT_ID, self::USER_ID, self::THREAD_ID);

    self::assertSame(self::CHAT_ID, $result['chatId']);
    self::assertSame(self::USER_ID, $result['userId']);
    self::assertSame(self::THREAD_ID, $result['threadId']);
  }

  /**
   * `UserInTopic::apply()` passes `null` through when no thread ID is given.
   */
  public function testUserInTopicPassesNullThreadWhenAbsent(): void
  {
    $result = FsmStrategy::UserInTopic->apply(self::CHAT_ID, self::USER_ID);

    self::assertNull($result['threadId']);
  }

  // ------------------------------------------------------------------ //
  // ChatTopic
  // ------------------------------------------------------------------ //

  /**
   * `ChatTopic::apply()` returns `(chatId, chatId, threadId)` — userId is normalised to chatId.
   *
   * Mirrors upstream: `return chat_id, chat_id, thread_id`.
   */
  public function testChatTopicReturnsChatIdForBothIdsAndPreservesThread(): void
  {
    $result = FsmStrategy::ChatTopic->apply(self::CHAT_ID, self::USER_ID, self::THREAD_ID);

    self::assertSame(self::CHAT_ID, $result['chatId']);
    self::assertSame(self::CHAT_ID, $result['userId'], 'ChatTopic strategy must set userId = chatId');
    self::assertSame(self::THREAD_ID, $result['threadId']);
  }

  /**
   * `ChatTopic::apply()` passes `null` thread through when no thread ID is given.
   */
  public function testChatTopicPassesNullThreadWhenAbsent(): void
  {
    $result = FsmStrategy::ChatTopic->apply(self::CHAT_ID, self::USER_ID);

    self::assertNull($result['threadId']);
  }

  // ------------------------------------------------------------------ //
  // Return shape
  // ------------------------------------------------------------------ //

  /**
   * `apply()` always returns an array with exactly the three expected keys.
   */
  public function testApplyReturnShapeHasExpectedKeys(): void
  {
    $result = FsmStrategy::UserInChat->apply(self::CHAT_ID, self::USER_ID);

    self::assertArrayHasKey('chatId', $result);
    self::assertArrayHasKey('userId', $result);
    self::assertArrayHasKey('threadId', $result);
    self::assertCount(3, $result);
  }
}
