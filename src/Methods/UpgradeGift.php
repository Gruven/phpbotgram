<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Upgrades a given regular gift to a unique gift. Requires the can_transfer_and_upgrade_gifts business bot right. Additionally requires the can_transfer_stars business bot right if the upgrade is paid. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#upgradegift
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class UpgradeGift extends TelegramMethod
{
  public const string ApiMethod = 'upgradeGift';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $businessConnectionId,
    public readonly string $ownedGiftId,
    public readonly ?bool $keepOriginalDetails = null,
    public readonly ?int $starCount = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
