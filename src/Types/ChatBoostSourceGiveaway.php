<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * The boost was obtained by the creation of a Telegram Premium or a Telegram Star giveaway. This boosts the chat 4 times for the duration of the corresponding Telegram Premium subscription for Telegram Premium giveaways and prize_star_count / 500 times for one year for Telegram Star giveaways.
 *
 * Source: https://core.telegram.org/bots/api#chatboostsourcegiveaway
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChatBoostSourceGiveaway extends ChatBoostSource
{
  public function __construct(
    public readonly string $source,
    public readonly int $giveawayMessageId,
    public readonly ?User $user = null,
    public readonly ?int $prizeStarCount = null,
    public readonly ?bool $isUnclaimed = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
