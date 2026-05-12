<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents the scope of bot commands, covering all group and supergroup chat administrators.
 *
 * Source: https://core.telegram.org/bots/api#botcommandscopeallchatadministrators
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BotCommandScopeAllChatAdministrators extends BotCommandScope
{
  public function __construct(
    public readonly string $type = 'all_chat_administrators',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
