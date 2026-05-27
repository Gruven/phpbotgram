<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents the scope of bot commands, covering a specific chat.
 *
 * Source: https://core.telegram.org/bots/api#botcommandscopechat
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BotCommandScopeChat extends BotCommandScope
{
  public function __construct(
    public readonly int|string $chatId,
    public readonly string $type = 'chat',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
