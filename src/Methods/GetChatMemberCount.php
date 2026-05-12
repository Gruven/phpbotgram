<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to get the number of members in a chat. Returns Int on success.
 *
 * Source: https://core.telegram.org/bots/api#getchatmembercount
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<int>
 */
final class GetChatMemberCount extends TelegramMethod
{
  public const string ApiMethod = 'getChatMemberCount';
  public const string ReturnsType = 'int';

  public function __construct(
    public readonly int|string $chatId,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
