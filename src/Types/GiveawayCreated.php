<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a service message about the creation of a scheduled giveaway.
 *
 * Source: https://core.telegram.org/bots/api#giveawaycreated
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class GiveawayCreated extends TelegramObject
{
  public function __construct(
    public readonly ?int $prizeStarCount = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
