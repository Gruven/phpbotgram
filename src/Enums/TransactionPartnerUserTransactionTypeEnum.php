<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * This object represents type of the transaction that were made by partner user.
 *
 * Source: https://core.telegram.org/bots/api#transactionpartneruser
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum TransactionPartnerUserTransactionTypeEnum: string
{
  case InvoicePayment = 'invoice_payment';
  case PaidMediaPayment = 'paid_media_payment';
  case GiftPurchase = 'gift_purchase';
  case PremiumPurchase = 'premium_purchase';
  case BusinessAccountTransfer = 'business_account_transfer';
}
