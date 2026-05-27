<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * This object represents owned gift type
 *
 * Source: https://core.telegram.org/bots/api#ownedgift
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum OwnedGiftType: string
{
  case Regular = 'regular';
  case Unique = 'unique';
}
