<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * This object represents a message about the completion of a giveaway with public winners.
 *
 * Source: https://core.telegram.org/bots/api#giveawaywinners
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class GiveawayWinners extends TelegramObject
{
  /**
   * @param list<User> $winners
   */
  public function __construct(
    public readonly Chat $chat,
    public readonly int $giveawayMessageId,
    public readonly DateTime $winnersSelectionDate,
    public readonly int $winnerCount,
    public readonly array $winners,
    public readonly ?int $additionalChatCount = null,
    public readonly ?int $prizeStarCount = null,
    public readonly ?int $premiumSubscriptionMonthCount = null,
    public readonly ?int $unclaimedPrizeCount = null,
    public readonly ?bool $onlyNewMembers = null,
    public readonly ?bool $wasRefunded = null,
    public readonly ?string $prizeDescription = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
