<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents a menu button, which opens the bot's list of commands.
 *
 * Source: https://core.telegram.org/bots/api#menubuttoncommands
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class MenuButtonCommands extends MenuButton
{
  public function __construct(
    public readonly string $type = 'commands',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
