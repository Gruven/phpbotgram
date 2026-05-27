<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * This object represents a type of chat boost source.
 *
 * Source: https://core.telegram.org/bots/api#chatboostsource
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum ChatBoostSourceType: string
{
  case Premium = 'premium';
  case GiftCode = 'gift_code';
  case Giveaway = 'giveaway';
}
