<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents the scope of bot commands, covering all group and supergroup chats.
 *
 * Source: https://core.telegram.org/bots/api#botcommandscopeallgroupchats
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BotCommandScopeAllGroupChats extends BotCommandScope
{
  public function __construct(
    public readonly string $type = 'all_group_chats',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
