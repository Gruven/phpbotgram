<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents the rights of a business bot.
 *
 * Source: https://core.telegram.org/bots/api#businessbotrights
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BusinessBotRights extends TelegramObject
{
  public function __construct(
    public readonly ?bool $canReply = null,
    public readonly ?bool $canReadMessages = null,
    public readonly ?bool $canDeleteSentMessages = null,
    public readonly ?bool $canDeleteAllMessages = null,
    public readonly ?bool $canEditName = null,
    public readonly ?bool $canEditBio = null,
    public readonly ?bool $canEditProfilePhoto = null,
    public readonly ?bool $canEditUsername = null,
    public readonly ?bool $canChangeGiftSettings = null,
    public readonly ?bool $canViewGiftsAndStars = null,
    public readonly ?bool $canConvertGiftsToStars = null,
    public readonly ?bool $canTransferAndUpgradeGifts = null,
    public readonly ?bool $canTransferStars = null,
    public readonly ?bool $canManageStories = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
