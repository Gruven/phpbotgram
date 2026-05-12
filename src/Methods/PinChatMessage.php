<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to add a message to the list of pinned messages in a chat. In private chats and channel direct messages chats, all non-service messages can be pinned. Conversely, the bot must be an administrator with the 'can_pin_messages' right or the 'can_edit_messages' right to pin messages in groups and channels respectively. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#pinchatmessage
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class PinChatMessage extends TelegramMethod
{
  public const string ApiMethod = 'pinChatMessage';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int|string $chatId,
    public readonly int $messageId,
    public readonly ?string $businessConnectionId = null,
    public readonly ?bool $disableNotification = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
