<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to remove a reaction from a message in a group or a supergroup chat. The bot must have the 'can_delete_messages' administrator right in the chat. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#deletemessagereaction
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class DeleteMessageReaction extends TelegramMethod
{
  public const string ApiMethod = 'deleteMessageReaction';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int|string $chatId,
    public readonly int $messageId,
    public readonly ?int $userId = null,
    public readonly ?int $actorChatId = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
