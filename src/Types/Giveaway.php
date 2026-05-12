<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * This object represents a message about a scheduled giveaway.
 *
 * Source: https://core.telegram.org/bots/api#giveaway
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class Giveaway extends TelegramObject
{
  /**
   * @param list<Chat> $chats
   * @param list<string> $countryCodes
   */
  public function __construct(
    public readonly array $chats,
    public readonly DateTime $winnersSelectionDate,
    public readonly int $winnerCount,
    public readonly ?bool $onlyNewMembers = null,
    public readonly ?bool $hasPublicWinners = null,
    public readonly ?string $prizeDescription = null,
    public readonly ?array $countryCodes = null,
    public readonly ?int $prizeStarCount = null,
    public readonly ?int $premiumSubscriptionMonthCount = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
