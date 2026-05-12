<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object describes the state of a revenue withdrawal operation. Currently, it can be one of
 *  - RevenueWithdrawalStatePending
 *  - RevenueWithdrawalStateSucceeded
 *  - RevenueWithdrawalStateFailed
 *
 * Source: https://core.telegram.org/bots/api#revenuewithdrawalstate
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
abstract class RevenueWithdrawalState extends TelegramObject
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
