<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes an amount of Telegram Stars.
 *
 * Source: https://core.telegram.org/bots/api#staramount
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class StarAmount extends TelegramObject
{
  public function __construct(
    public readonly int $amount,
    public readonly ?int $nanostarAmount = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
