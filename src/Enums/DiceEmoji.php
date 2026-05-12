<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * Emoji on which the dice throw animation is based
 *
 * Source: https://core.telegram.org/bots/api#dice
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum DiceEmoji: string
{
  case Dice = '🎲';
  case Dart = '🎯';
  case Basketball = '🏀';
  case Football = '⚽';
  case SlotMachine = '🎰';
  case Bowling = '🎳';
}
