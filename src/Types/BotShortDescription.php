<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents the bot's short description.
 *
 * Source: https://core.telegram.org/bots/api#botshortdescription
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BotShortDescription extends TelegramObject
{
  public function __construct(
    public readonly string $shortDescription,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
