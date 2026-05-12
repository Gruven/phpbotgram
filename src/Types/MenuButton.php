<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object describes the bot's menu button in a private chat. It should be one of
 *  - MenuButtonCommands
 *  - MenuButtonWebApp
 *  - MenuButtonDefault
 * If a menu button other than MenuButtonDefault is set for a private chat, then it is applied in the chat. Otherwise the default menu button is applied. By default, the menu button opens the list of bot commands.
 *
 * Source: https://core.telegram.org/bots/api#menubutton
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
abstract class MenuButton extends MutableTelegramObject
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
