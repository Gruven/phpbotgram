<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\ChatFullInfo;

/**
 * Use this method to get up-to-date information about the chat. Returns a ChatFullInfo object on success.
 *
 * Source: https://core.telegram.org/bots/api#getchat
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<ChatFullInfo>
 */
final class GetChat extends TelegramMethod
{
  public const string ApiMethod = 'getChat';
  public const string ReturnsType = ChatFullInfo::class;

  public function __construct(
    public readonly int|string $chatId,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
