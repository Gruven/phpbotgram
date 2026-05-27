<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * The withdrawal is in progress.
 *
 * Source: https://core.telegram.org/bots/api#revenuewithdrawalstatepending
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RevenueWithdrawalStatePending extends RevenueWithdrawalState
{
  public function __construct(
    public readonly string $type = 'pending',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
