<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents the scope of bot commands, covering all private chats.
 *
 * Source: https://core.telegram.org/bots/api#botcommandscopeallprivatechats
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BotCommandScopeAllPrivateChats extends BotCommandScope
{
  public function __construct(
    public readonly string $type = 'all_private_chats',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
