<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a gift that can be sent by the bot.
 *
 * Source: https://core.telegram.org/bots/api#gift
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class Gift extends TelegramObject
{
  public function __construct(
    public readonly string $id,
    public readonly Sticker $sticker,
    public readonly int $starCount,
    public readonly ?int $upgradeStarCount = null,
    public readonly ?bool $isPremium = null,
    public readonly ?bool $hasColors = null,
    public readonly ?int $totalCount = null,
    public readonly ?int $remainingCount = null,
    public readonly ?int $personalTotalCount = null,
    public readonly ?int $personalRemainingCount = null,
    public readonly ?GiftBackground $background = null,
    public readonly ?int $uniqueGiftVariantCount = null,
    public readonly ?Chat $publisherChat = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
