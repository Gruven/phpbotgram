<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a withdrawal transaction to the Telegram Ads platform.
 *
 * Source: https://core.telegram.org/bots/api#transactionpartnertelegramads
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class TransactionPartnerTelegramAds extends TransactionPartner
{
  public function __construct(
    public readonly string $type = 'telegram_ads',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
