<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a transaction with a user.
 *
 * Source: https://core.telegram.org/bots/api#transactionpartneruser
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class TransactionPartnerUser extends TransactionPartner
{
  /**
   * @param null|list<PaidMedia> $paidMedia
   */
  public function __construct(
    public readonly string $transactionType,
    public readonly User $user,
    public readonly string $type = 'user',
    public readonly ?AffiliateInfo $affiliate = null,
    public readonly ?string $invoicePayload = null,
    public readonly ?int $subscriptionPeriod = null,
    public readonly ?array $paidMedia = null,
    public readonly ?string $paidMediaPayload = null,
    public readonly ?Gift $gift = null,
    public readonly ?int $premiumSubscriptionDuration = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
