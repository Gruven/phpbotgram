<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to delete a chat photo. Photos can't be changed for private chats. The bot must be an administrator in the chat for this to work and must have the appropriate administrator rights. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#deletechatphoto
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class DeleteChatPhoto extends TelegramMethod
{
  public const string ApiMethod = 'deleteChatPhoto';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int|string $chatId,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
