<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Refunds a successful payment in Telegram Stars. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#refundstarpayment
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class RefundStarPayment extends TelegramMethod
{
  public const string ApiMethod = 'refundStarPayment';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int $userId,
    public readonly string $telegramPaymentChargeId,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
