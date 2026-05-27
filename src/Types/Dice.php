<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents an animated emoji that displays a random value.
 *
 * Source: https://core.telegram.org/bots/api#dice
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class Dice extends TelegramObject
{
  public function __construct(
    public readonly string $emoji,
    public readonly int $value,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
