<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * The withdrawal failed and the transaction was refunded.
 *
 * Source: https://core.telegram.org/bots/api#revenuewithdrawalstatefailed
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RevenueWithdrawalStateFailed extends RevenueWithdrawalState
{
  public function __construct(
    public readonly string $type = 'failed',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
