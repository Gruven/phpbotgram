<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a regular gift owned by a user or a chat.
 *
 * Source: https://core.telegram.org/bots/api#ownedgiftregular
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class OwnedGiftRegular extends OwnedGift
{
  /**
   * @param null|list<MessageEntity> $entities
   */
  public function __construct(
    public readonly Gift $gift,
    public readonly int $sendDate,
    public readonly string $type = 'regular',
    public readonly ?string $ownedGiftId = null,
    public readonly ?User $senderUser = null,
    public readonly ?string $text = null,
    public readonly ?array $entities = null,
    public readonly ?bool $isPrivate = null,
    public readonly ?bool $isSaved = null,
    public readonly ?bool $canBeUpgraded = null,
    public readonly ?bool $wasRefunded = null,
    public readonly ?int $convertStarCount = null,
    public readonly ?int $prepaidUpgradeStarCount = null,
    public readonly ?bool $isUpgradeSeparate = null,
    public readonly ?int $uniqueGiftNumber = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
