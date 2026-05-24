<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm;

use Gruven\PhpBotGram\Fsm\FsmStrategy;
use PHPUnit\Framework\TestCase;

/**
 * Upstream `tests/test_fsm/test_strategy.py` cases deliberately not ported:
 *
 * - No deliberate skips. All `TestStrategy::test_strategy` parametrize rows
 *   are ported in this file.
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 *
 * @internal
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
  // PRIVATE case (chat_id == user_id — DM / inline context)
  // Mirrors upstream parametrize rows where PRIVATE = (USER_ID, USER_ID, None)
  // ------------------------------------------------------------------ //

  /**
   * `UserInChat` PRIVATE case: chat and user ids are already equal, thread null.
   *
   * Upstream row `[FSMStrategy.USER_IN_CHAT, PRIVATE, PRIVATE]`.
   */
  public function testUserInChatPrivateCasePassesThrough(): void
  {
    $result = FsmStrategy::UserInChat->apply(self::USER_ID, self::USER_ID, null);

    self::assertSame(self::USER_ID, $result['chatId']);
    self::assertSame(self::USER_ID, $result['userId']);
    self::assertNull($result['threadId']);
  }

  /**
   * `Chat` PRIVATE case: chatId == userId, both slots become userId.
   *
   * Upstream row `[FSMStrategy.CHAT, PRIVATE, (USER_ID, USER_ID, None)]`.
   */
  public function testChatPrivateCaseMirrorsUserId(): void
  {
    $result = FsmStrategy::Chat->apply(self::USER_ID, self::USER_ID, null);

    self::assertSame(self::USER_ID, $result['chatId']);
    self::assertSame(self::USER_ID, $result['userId']);
    self::assertNull($result['threadId']);
  }

  /**
   * `GlobalUser` PRIVATE case: userId used for both slots, thread null.
   *
   * Upstream row `[FSMStrategy.GLOBAL_USER, PRIVATE, PRIVATE]`.
   */
  public function testGlobalUserPrivateCasePreservesUserId(): void
  {
    $result = FsmStrategy::GlobalUser->apply(self::USER_ID, self::USER_ID, null);

    self::assertSame(self::USER_ID, $result['chatId']);
    self::assertSame(self::USER_ID, $result['userId']);
    self::assertNull($result['threadId']);
  }

  /**
   * `UserInTopic` PRIVATE case: no thread, both ids preserved.
   *
   * Upstream row `[FSMStrategy.USER_IN_TOPIC, PRIVATE, PRIVATE]`.
   */
  public function testUserInTopicPrivateCasePreservesBothIds(): void
  {
    $result = FsmStrategy::UserInTopic->apply(self::USER_ID, self::USER_ID, null);

    self::assertSame(self::USER_ID, $result['chatId']);
    self::assertSame(self::USER_ID, $result['userId']);
    self::assertNull($result['threadId']);
  }

  /**
   * `ChatTopic` PRIVATE case: chatId == userId, no thread → userId for both.
   *
   * Upstream row `[FSMStrategy.CHAT_TOPIC, PRIVATE, PRIVATE]`.
   */
  public function testChatTopicPrivateCaseMirrorsUserId(): void
  {
    $result = FsmStrategy::ChatTopic->apply(self::USER_ID, self::USER_ID, null);

    self::assertSame(self::USER_ID, $result['chatId']);
    self::assertSame(self::USER_ID, $result['userId']);
    self::assertNull($result['threadId']);
  }

  // ------------------------------------------------------------------ //
  // THREAD case parametrize rows
  // Mirrors upstream parametrize rows where THREAD = (CHAT_ID, USER_ID, THREAD_ID)
  // ------------------------------------------------------------------ //

  /**
   * `UserInChat` THREAD case: thread is discarded → same as CHAT case.
   *
   * Upstream row `[FSMStrategy.USER_IN_CHAT, THREAD, CHAT]`.
   */
  public function testUserInChatThreadCaseDiscardsThread(): void
  {
    $result = FsmStrategy::UserInChat->apply(self::CHAT_ID, self::USER_ID, self::THREAD_ID);

    self::assertSame(self::CHAT_ID, $result['chatId']);
    self::assertSame(self::USER_ID, $result['userId']);
    self::assertNull($result['threadId']);
  }

  /**
   * `Chat` THREAD case: both slots become chatId, thread discarded.
   *
   * Upstream row `[FSMStrategy.CHAT, THREAD, (CHAT_ID, CHAT_ID, None)]`.
   */
  public function testChatThreadCaseUsesChatIdAndDiscardsThread(): void
  {
    $result = FsmStrategy::Chat->apply(self::CHAT_ID, self::USER_ID, self::THREAD_ID);

    self::assertSame(self::CHAT_ID, $result['chatId']);
    self::assertSame(self::CHAT_ID, $result['userId']);
    self::assertNull($result['threadId']);
  }

  /**
   * `GlobalUser` THREAD case: both slots become userId, thread discarded.
   *
   * Upstream row `[FSMStrategy.GLOBAL_USER, THREAD, PRIVATE]`.
   */
  public function testGlobalUserThreadCaseUsesUserIdAndDiscardsThread(): void
  {
    $result = FsmStrategy::GlobalUser->apply(self::CHAT_ID, self::USER_ID, self::THREAD_ID);

    self::assertSame(self::USER_ID, $result['chatId']);
    self::assertSame(self::USER_ID, $result['userId']);
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
