<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * Describes a unique gift received and owned by a user or a chat.
 *
 * Source: https://core.telegram.org/bots/api#ownedgiftunique
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class OwnedGiftUnique extends OwnedGift
{
  public function __construct(
    public readonly string $type,
    public readonly UniqueGift $gift,
    public readonly ?string $ownedGiftId,
    public readonly ?User $senderUser,
    public readonly int $sendDate,
    public readonly ?bool $isSaved = null,
    public readonly ?bool $canBeTransferred = null,
    public readonly ?int $transferStarCount = null,
    public readonly ?DateTime $nextTransferDate = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
