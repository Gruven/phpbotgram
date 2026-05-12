<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * The background is a gradient fill.
 *
 * Source: https://core.telegram.org/bots/api#backgroundfillgradient
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BackgroundFillGradient extends BackgroundFill
{
  public function __construct(
    public readonly string $type,
    public readonly int $topColor,
    public readonly int $bottomColor,
    public readonly int $rotationAngle,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
