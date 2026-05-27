<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object describes a unique gift that was upgraded from a regular gift.
 *
 * Source: https://core.telegram.org/bots/api#uniquegift
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class UniqueGift extends TelegramObject
{
  public function __construct(
    public readonly string $giftId,
    public readonly string $baseName,
    public readonly string $name,
    public readonly int $number,
    public readonly UniqueGiftModel $model,
    public readonly UniqueGiftSymbol $symbol,
    public readonly UniqueGiftBackdrop $backdrop,
    public readonly ?bool $isPremium = null,
    public readonly ?bool $isBurned = null,
    public readonly ?bool $isFromBlockchain = null,
    public readonly ?UniqueGiftColors $colors = null,
    public readonly ?Chat $publisherChat = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
