<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * This object represents a type of transaction partner.
 *
 * Source: https://core.telegram.org/bots/api#transactionpartner
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum TransactionPartnerType: string
{
  case Fragment = 'fragment';
  case Other = 'other';
  case User = 'user';
  case TelegramAds = 'telegram_ads';
  case TelegramApi = 'telegram_api';
  case AffiliateProgram = 'affiliate_program';
  case Chat = 'chat';
}
