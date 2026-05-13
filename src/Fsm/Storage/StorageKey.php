<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Storage;

/**
 * Immutable contextual key that identifies a single FSM record. Every FSM
 * storage operation is addressed by one of these keys; the key encodes the
 * Telegram context (bot, chat, thread, user, optional business connection)
 * plus an optional `destiny` tag for advanced multi-slot scenarios.
 *
 * Mirrors `aiogram.fsm.storage.base.StorageKey` (`@dataclass(frozen=True)`,
 * `aiogram/fsm/storage/base.py:14-21`). The frozen-dataclass guarantee maps
 * directly to PHP's `final readonly class`, which prohibits mutation after
 * construction and prevents subclassing.
 *
 * Field mapping (upstream → PHP):
 *   - `bot_id`                  → `$botId`
 *   - `chat_id`                 → `$chatId`
 *   - `user_id`                 → `$userId`
 *   - `thread_id`               → `$threadId`
 *   - `business_connection_id`  → `$businessConnectionId`
 *   - `destiny`                 → `$destiny`
 */
final readonly class StorageKey
{
  /**
   * Wire-level sentinel for the default destiny slot.
   *
   * Mirrors `DEFAULT_DESTINY = "default"` at
   * `aiogram/fsm/storage/base.py:11`. Declared as a class constant so
   * callers can reference it as `StorageKey::DEFAULT_DESTINY` without
   * importing a free-standing constant.
   */
  public const string DEFAULT_DESTINY = 'default';

  /**
   * @param int $botId Telegram Bot ID — identifies which bot token owns this record.
   * @param int $chatId Telegram Chat ID — the conversation the record belongs to.
   * @param int $userId Telegram User ID — the participant within that chat.
   * @param null|int $threadId Optional message-thread (forum topic) ID within a supergroup.
   * @param null|string $businessConnectionId Optional business connection identifier.
   * @param string $destiny Destiny tag for multi-slot usage; defaults to {@see self::DEFAULT_DESTINY}.
   */
  public function __construct(
    public int $botId,
    public int $chatId,
    public int $userId,
    public ?int $threadId = null,
    public ?string $businessConnectionId = null,
    public string $destiny = self::DEFAULT_DESTINY,
  ) {}

  /**
   * Return a new `StorageKey` identical to this one but with a different destiny.
   *
   * Because `StorageKey` is `final readonly`, mutation is impossible — this
   * factory method produces a fresh instance with all fields copied and only
   * `$destiny` replaced. Used by `HistoryManager` to derive a separate storage
   * slot for the history stack without affecting the main FSM slot.
   *
   * Mirrors the `replace(state.key, destiny=destiny)` call in
   * `aiogram/fsm/scene.py:52` (Python's `dataclasses.replace` equivalent).
   *
   * @param string $destiny The new destiny tag for the returned key.
   *
   * @return self A new instance with the replaced destiny.
   */
  public function withDestiny(string $destiny): self
  {
    return new self(
      botId: $this->botId,
      chatId: $this->chatId,
      userId: $this->userId,
      threadId: $this->threadId,
      businessConnectionId: $this->businessConnectionId,
      destiny: $destiny,
    );
  }
}
