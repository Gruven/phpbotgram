<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Contains information about the affiliate that received a commission via this transaction.
 *
 * Source: https://core.telegram.org/bots/api#affiliateinfo
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class AffiliateInfo extends TelegramObject
{
  public function __construct(
    public readonly ?User $affiliateUser,
    public readonly ?Chat $affiliateChat,
    public readonly int $commissionPerMille,
    public readonly int $amount,
    public readonly ?int $nanostarAmount = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
