<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * The background is filled using the selected color.
 *
 * Source: https://core.telegram.org/bots/api#backgroundfillsolid
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BackgroundFillSolid extends BackgroundFill
{
  public function __construct(
    public readonly string $type,
    public readonly int $color,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
