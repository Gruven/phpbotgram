<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents the scope of bot commands, covering all administrators of a specific group or supergroup chat.
 *
 * Source: https://core.telegram.org/bots/api#botcommandscopechatadministrators
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BotCommandScopeChatAdministrators extends BotCommandScope
{
  public function __construct(
    public readonly int|string $chatId,
    public readonly string $type = 'chat_administrators',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
