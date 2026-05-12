<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\MenuButtonCommands;
use Gruven\PhpBotGram\Types\MenuButtonDefault;
use Gruven\PhpBotGram\Types\MenuButtonWebApp;

/**
 * Use this method to change the bot's menu button in a private chat, or the default menu button. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#setchatmenubutton
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetChatMenuButton extends TelegramMethod
{
  public const string ApiMethod = 'setChatMenuButton';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly ?int $chatId = null,
    public readonly null|MenuButtonCommands|MenuButtonDefault|MenuButtonWebApp $menuButton = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
