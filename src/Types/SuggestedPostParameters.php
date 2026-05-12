<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * Contains parameters of a post that is being suggested by the bot.
 *
 * Source: https://core.telegram.org/bots/api#suggestedpostparameters
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class SuggestedPostParameters extends TelegramObject
{
  public function __construct(
    public readonly ?SuggestedPostPrice $price = null,
    public readonly ?DateTime $sendDate = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
