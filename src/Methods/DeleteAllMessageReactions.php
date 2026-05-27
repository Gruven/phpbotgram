<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to remove up to 10000 recent reactions in a group or a supergroup chat added by a given user or chat. The bot must have the 'can_delete_messages' administrator right in the chat. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#deleteallmessagereactions
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class DeleteAllMessageReactions extends TelegramMethod
{
  public const string ApiMethod = 'deleteAllMessageReactions';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int|string $chatId,
    public readonly ?int $userId = null,
    public readonly ?int $actorChatId = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
