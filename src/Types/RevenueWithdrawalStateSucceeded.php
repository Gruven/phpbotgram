<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * The withdrawal succeeded.
 *
 * Source: https://core.telegram.org/bots/api#revenuewithdrawalstatesucceeded
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RevenueWithdrawalStateSucceeded extends RevenueWithdrawalState
{
  public function __construct(
    public readonly string $type,
    public readonly DateTime $date,
    public readonly string $url,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
