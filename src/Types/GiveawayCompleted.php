<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a service message about the completion of a giveaway without public winners.
 *
 * Source: https://core.telegram.org/bots/api#giveawaycompleted
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class GiveawayCompleted extends TelegramObject
{
  public function __construct(
    public readonly int $winnerCount,
    public readonly ?int $unclaimedPrizeCount = null,
    public readonly ?Message $giveawayMessage = null,
    public readonly ?bool $isStarGiveaway = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
