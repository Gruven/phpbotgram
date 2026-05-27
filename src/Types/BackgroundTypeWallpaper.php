<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * The background is a wallpaper in the JPEG format.
 *
 * Source: https://core.telegram.org/bots/api#backgroundtypewallpaper
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BackgroundTypeWallpaper extends BackgroundType
{
  public function __construct(
    public readonly Document $document,
    public readonly int $darkThemeDimming,
    public readonly string $type = 'wallpaper',
    public readonly ?bool $isBlurred = null,
    public readonly ?bool $isMoving = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
