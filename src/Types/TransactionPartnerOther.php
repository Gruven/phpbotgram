<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a transaction with an unknown source or recipient.
 *
 * Source: https://core.telegram.org/bots/api#transactionpartnerother
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class TransactionPartnerOther extends TransactionPartner
{
  public function __construct(
    public readonly string $type = 'other',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
