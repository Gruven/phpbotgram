<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Returns the list of gifts that can be sent by the bot to users and channel chats. Requires no parameters. Returns a Gifts object.
 *
 * Source: https://core.telegram.org/bots/api#getavailablegifts
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class GetAvailableGifts extends TelegramMethod
{
  public const string ApiMethod = 'getAvailableGifts';
  public const string ReturnsType = 'bool';

  public function __construct(?Bot $bot = null)
  {
    parent::__construct($bot);
  }
}
