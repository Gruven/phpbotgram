<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\MenuButton;

/**
 * Use this method to get the current value of the bot's menu button in a private chat, or the default menu button. Returns MenuButton on success.
 *
 * Source: https://core.telegram.org/bots/api#getchatmenubutton
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<MenuButton>
 */
final class GetChatMenuButton extends TelegramMethod
{
  public const string ApiMethod = 'getChatMenuButton';
  public const string ReturnsType = MenuButton::class;

  public function __construct(
    public readonly ?int $chatId = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
