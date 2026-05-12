<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a transaction with payment for paid broadcasting.
 *
 * Source: https://core.telegram.org/bots/api#transactionpartnertelegramapi
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class TransactionPartnerTelegramApi extends TransactionPartner
{
  public function __construct(
    public readonly int $requestCount,
    public readonly string $type = 'telegram_api',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
