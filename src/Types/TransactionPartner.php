<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object describes the source of a transaction, or its recipient for outgoing transactions. Currently, it can be one of
 *  - TransactionPartnerUser
 *  - TransactionPartnerChat
 *  - TransactionPartnerAffiliateProgram
 *  - TransactionPartnerFragment
 *  - TransactionPartnerTelegramAds
 *  - TransactionPartnerTelegramApi
 *  - TransactionPartnerOther
 *
 * Source: https://core.telegram.org/bots/api#transactionpartner
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
abstract class TransactionPartner extends TelegramObject
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
