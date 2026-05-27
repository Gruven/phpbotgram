<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * Describes a service message about a unique gift that was sent or received.
 *
 * Source: https://core.telegram.org/bots/api#uniquegiftinfo
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class UniqueGiftInfo extends TelegramObject
{
  public function __construct(
    public readonly UniqueGift $gift,
    public readonly string $origin,
    public readonly ?string $lastResaleCurrency = null,
    public readonly ?int $lastResaleAmount = null,
    public readonly ?string $ownedGiftId = null,
    public readonly ?int $transferStarCount = null,
    public readonly ?DateTime $nextTransferDate = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
