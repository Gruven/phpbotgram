<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a service message about a regular gift that was sent or received.
 *
 * Source: https://core.telegram.org/bots/api#giftinfo
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class GiftInfo extends TelegramObject
{
  /**
   * @param null|list<MessageEntity> $entities
   */
  public function __construct(
    public readonly Gift $gift,
    public readonly ?string $ownedGiftId = null,
    public readonly ?int $convertStarCount = null,
    public readonly ?int $prepaidUpgradeStarCount = null,
    public readonly ?bool $isUpgradeSeparate = null,
    public readonly ?bool $canBeUpgraded = null,
    public readonly ?string $text = null,
    public readonly ?array $entities = null,
    public readonly ?bool $isPrivate = null,
    public readonly ?int $uniqueGiftNumber = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
