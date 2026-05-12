<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * An animated profile photo in the MPEG4 format.
 *
 * Source: https://core.telegram.org/bots/api#inputprofilephotoanimated
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputProfilePhotoAnimated extends InputProfilePhoto
{
  public function __construct(
    public readonly InputFile|string $animation,
    public readonly string $type = 'animated',
    public readonly ?float $mainFrameTimestamp = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
