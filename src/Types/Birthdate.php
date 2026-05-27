<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes the birthdate of a user.
 *
 * Source: https://core.telegram.org/bots/api#birthdate
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class Birthdate extends TelegramObject
{
  public function __construct(
    public readonly int $day,
    public readonly int $month,
    public readonly ?int $year = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
