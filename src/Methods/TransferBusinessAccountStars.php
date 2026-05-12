<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Transfers Telegram Stars from the business account balance to the bot's balance. Requires the can_transfer_stars business bot right. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#transferbusinessaccountstars
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class TransferBusinessAccountStars extends TelegramMethod
{
  public const string ApiMethod = 'transferBusinessAccountStars';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $businessConnectionId,
    public readonly int $starCount,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
