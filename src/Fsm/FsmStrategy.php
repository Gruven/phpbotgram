<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm;

/**
 * Determines how FSM state is scoped to a Telegram context.
 *
 * Mirrors `aiogram.fsm.strategy.FSMStrategy` (`aiogram/fsm/strategy.py`).
 * Python uses `auto()` (non-backed); PHP mirrors this as a non-backed enum
 * with PascalCase cases per PHP enum convention.
 *
 * The `apply()` method replaces the upstream module-level `apply_strategy()`
 * free function — attaching the logic to the enum case itself is idiomatic
 * PHP and keeps the API self-contained.
 *
 * Case mapping (upstream → PHP):
 *   - `USER_IN_CHAT`  → `UserInChat`  — state per user in a specific chat (default).
 *   - `CHAT`          → `Chat`        — state per chat globally (shared across users).
 *   - `GLOBAL_USER`   → `GlobalUser`  — state per user globally (shared across chats).
 *   - `USER_IN_TOPIC` → `UserInTopic` — state per user in a specific chat and topic.
 *   - `CHAT_TOPIC`    → `ChatTopic`   — state per chat and topic (shared across users).
 */
enum FsmStrategy
{
  /**
   * State is scoped per user within a specific chat.
   *
   * This is the default strategy. Thread ID is discarded (set to `null`).
   * Mirrors `FSMStrategy.USER_IN_CHAT`.
   */
  case UserInChat;

  /**
   * State is scoped to the entire chat, shared by all users.
   *
   * Both `chatId` and `userId` are set to `$chatId`; thread ID is discarded.
   * Mirrors `FSMStrategy.CHAT`.
   */
  case Chat;

  /**
   * State is scoped to the user across all chats.
   *
   * Both `chatId` and `userId` are set to `$userId`; thread ID is discarded.
   * Mirrors `FSMStrategy.GLOBAL_USER`.
   */
  case GlobalUser;

  /**
   * State is scoped per user within a specific chat and message thread.
   *
   * Thread ID is preserved as-is.
   * Mirrors `FSMStrategy.USER_IN_TOPIC`.
   */
  case UserInTopic;

  /**
   * State is scoped to a chat and thread, shared by all users.
   *
   * Both `chatId` and `userId` are set to `$chatId`; thread ID is preserved.
   * Mirrors `FSMStrategy.CHAT_TOPIC`.
   */
  case ChatTopic;

  /**
   * Apply this strategy to produce the canonical (chatId, userId, threadId)
   * tuple used as the FSM storage key.
   *
   * Mirrors the upstream module-level `apply_strategy()` free function
   * (`aiogram/fsm/strategy.py`).
   *
   * Return shape: `array{chatId: int, userId: int, threadId: ?int}`.
   *
   * @param int $chatId The Telegram chat ID of the event.
   * @param int $userId The Telegram user ID of the event.
   * @param ?int $threadId The optional message-thread (forum topic) ID.
   *
   * @return array{chatId: int, userId: int, threadId: ?int}
   */
  public function apply(int $chatId, int $userId, ?int $threadId = null): array
  {
    return match ($this) {
      self::Chat         => ['chatId' => $chatId, 'userId' => $chatId, 'threadId' => null],
      self::GlobalUser   => ['chatId' => $userId, 'userId' => $userId, 'threadId' => null],
      self::UserInTopic  => ['chatId' => $chatId, 'userId' => $userId, 'threadId' => $threadId],
      self::ChatTopic    => ['chatId' => $chatId, 'userId' => $chatId, 'threadId' => $threadId],
      // UserInChat is the default: user per chat, thread discarded.
      self::UserInChat   => ['chatId' => $chatId, 'userId' => $userId, 'threadId' => null],
    };
  }
}
