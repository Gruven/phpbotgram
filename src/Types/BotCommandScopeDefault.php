<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents the default scope of bot commands. Default commands are used if no commands with a narrower scope are specified for the user.
 *
 * Source: https://core.telegram.org/bots/api#botcommandscopedefault
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BotCommandScopeDefault extends BotCommandScope
{
  public function __construct(
    public readonly string $type = 'default',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
