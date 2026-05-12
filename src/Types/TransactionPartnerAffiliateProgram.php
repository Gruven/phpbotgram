<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes the affiliate program that issued the affiliate commission received via this transaction.
 *
 * Source: https://core.telegram.org/bots/api#transactionpartneraffiliateprogram
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class TransactionPartnerAffiliateProgram extends TransactionPartner
{
  public function __construct(
    public readonly string $type,
    public readonly ?User $sponsorUser,
    public readonly int $commissionPerMille,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
