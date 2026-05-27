<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A placeholder, currently holds no information. Use BotFather to set up your game.
 *
 * Source: https://core.telegram.org/bots/api#callbackgame
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class CallbackGame extends TelegramObject
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
