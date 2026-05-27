<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * This object represents input profile photo type
 *
 * Source: https://core.telegram.org/bots/api#inputprofilephoto
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum InputProfilePhotoType: string
{
  case Static = 'static';
  case Animated = 'animated';
}
