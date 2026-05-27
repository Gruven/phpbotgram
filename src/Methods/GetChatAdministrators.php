<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\ChatMember;

/**
 * Use this method to get a list of administrators in a chat. Returns an Array of ChatMember objects.
 *
 * Source: https://core.telegram.org/bots/api#getchatadministrators
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<list<ChatMember>>
 */
final class GetChatAdministrators extends TelegramMethod
{
  public const string ApiMethod = 'getChatAdministrators';
  public const string ReturnsType = 'list:ChatMember';

  public function __construct(
    public readonly int|string $chatId,
    public readonly ?bool $returnBots = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
