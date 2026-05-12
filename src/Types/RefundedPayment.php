<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object contains basic information about a refunded payment.
 *
 * Source: https://core.telegram.org/bots/api#refundedpayment
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RefundedPayment extends TelegramObject
{
  public function __construct(
    public readonly string $currency,
    public readonly int $totalAmount,
    public readonly string $invoicePayload,
    public readonly string $telegramPaymentChargeId,
    public readonly ?string $providerPaymentChargeId = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
