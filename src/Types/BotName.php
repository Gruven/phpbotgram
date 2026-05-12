<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents the bot's name.
 *
 * Source: https://core.telegram.org/bots/api#botname
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BotName extends TelegramObject
{
  public function __construct(
    public readonly string $name,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
