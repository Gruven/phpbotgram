<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Transfers an owned unique gift to another user. Requires the can_transfer_and_upgrade_gifts business bot right. Requires can_transfer_stars business bot right if the transfer is paid. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#transfergift
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class TransferGift extends TelegramMethod
{
  public const string ApiMethod = 'transferGift';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $businessConnectionId,
    public readonly string $ownedGiftId,
    public readonly int $newOwnerChatId,
    public readonly ?int $starCount = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
