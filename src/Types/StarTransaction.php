<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * Describes a Telegram Star transaction. Note that if the buyer initiates a chargeback with the payment provider from whom they acquired Stars (e.g., Apple, Google) following this transaction, the refunded Stars will be deducted from the bot's balance. This is outside of Telegram's control.
 *
 * Source: https://core.telegram.org/bots/api#startransaction
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class StarTransaction extends TelegramObject
{
  public function __construct(
    public readonly string $id,
    public readonly int $amount,
    public readonly ?int $nanostarAmount,
    public readonly DateTime $date,
    public readonly ?TransactionPartner $source = null,
    public readonly ?TransactionPartner $receiver = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
