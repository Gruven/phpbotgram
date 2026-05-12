<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a withdrawal transaction with Fragment.
 *
 * Source: https://core.telegram.org/bots/api#transactionpartnerfragment
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class TransactionPartnerFragment extends TransactionPartner
{
  public function __construct(
    public readonly string $type = 'fragment',
    public readonly ?RevenueWithdrawalState $withdrawalState = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
