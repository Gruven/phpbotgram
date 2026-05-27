<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A static profile photo in the .JPG format.
 *
 * Source: https://core.telegram.org/bots/api#inputprofilephotostatic
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputProfilePhotoStatic extends InputProfilePhoto
{
  public function __construct(
    public readonly InputFile|string $photo,
    public readonly string $type = 'static',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
