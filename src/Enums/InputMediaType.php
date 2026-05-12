<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * This object represents input media type
 *
 * Source: https://core.telegram.org/bots/api#inputmedia
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum InputMediaType: string
{
  case Animation = 'animation';
  case Audio = 'audio';
  case Document = 'document';
  case Photo = 'photo';
  case Video = 'video';
  case LivePhoto = 'live_photo';
  case Venue = 'venue';
  case Sticker = 'sticker';
  case Location = 'location';
}
