<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to remove a message from the list of pinned messages in a chat. In private chats and channel direct messages chats, all messages can be unpinned. Conversely, the bot must be an administrator with the 'can_pin_messages' right or the 'can_edit_messages' right to unpin messages in groups and channels respectively. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#unpinchatmessage
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class UnpinChatMessage extends TelegramMethod
{
  public const string ApiMethod = 'unpinChatMessage';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int|string $chatId,
    public readonly ?string $businessConnectionId = null,
    public readonly ?int $messageId = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
