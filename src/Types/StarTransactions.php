<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Contains a list of Telegram Star transactions.
 *
 * Source: https://core.telegram.org/bots/api#startransactions
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class StarTransactions extends TelegramObject
{
  /**
   * @param list<StarTransaction> $transactions
   */
  public function __construct(
    public readonly array $transactions,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
