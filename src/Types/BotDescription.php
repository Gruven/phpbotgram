<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents the bot's description.
 *
 * Source: https://core.telegram.org/bots/api#botdescription
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BotDescription extends TelegramObject
{
  public function __construct(
    public readonly string $description,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
