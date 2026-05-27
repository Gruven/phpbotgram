<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\ChatMember;

/**
 * Use this method to get information about a member of a chat. The method is only guaranteed to work for other users if the bot is an administrator in the chat. Returns a ChatMember object on success.
 *
 * Source: https://core.telegram.org/bots/api#getchatmember
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<ChatMember>
 */
final class GetChatMember extends TelegramMethod
{
  public const string ApiMethod = 'getChatMember';
  public const string ReturnsType = ChatMember::class;

  public function __construct(
    public readonly int|string $chatId,
    public readonly int $userId,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
