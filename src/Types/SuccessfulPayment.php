<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object contains basic information about a successful payment. Note that if the buyer initiates a chargeback with the relevant payment provider following this transaction, the funds may be debited from your balance. This is outside of Telegram's control.
 *
 * Source: https://core.telegram.org/bots/api#successfulpayment
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class SuccessfulPayment extends TelegramObject
{
  public function __construct(
    public readonly string $currency,
    public readonly int $totalAmount,
    public readonly string $invoicePayload,
    public readonly string $telegramPaymentChargeId,
    public readonly string $providerPaymentChargeId,
    public readonly ?int $subscriptionExpirationDate = null,
    public readonly ?bool $isRecurring = null,
    public readonly ?bool $isFirstRecurring = null,
    public readonly ?string $shippingOptionId = null,
    public readonly ?OrderInfo $orderInfo = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
