<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * Format of the sticker
 *
 * Source: https://core.telegram.org/bots/api#createnewstickerset
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum StickerFormat: string
{
  case Static = 'static';
  case Animated = 'animated';
  case Video = 'video';
}
