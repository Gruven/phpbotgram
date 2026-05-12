<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * The background is automatically filled based on the selected colors.
 *
 * Source: https://core.telegram.org/bots/api#backgroundtypefill
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BackgroundTypeFill extends BackgroundType
{
  public function __construct(
    public readonly string $type,
    public readonly BackgroundFill $fill,
    public readonly int $darkThemeDimming,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
