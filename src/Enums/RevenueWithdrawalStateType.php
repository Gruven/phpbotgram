<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * This object represents a revenue withdrawal state type
 *
 * Source: https://core.telegram.org/bots/api#revenuewithdrawalstate
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum RevenueWithdrawalStateType: string
{
  case Failed = 'failed';
  case Pending = 'pending';
  case Succeeded = 'succeeded';
}
