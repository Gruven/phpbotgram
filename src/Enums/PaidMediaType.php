<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * This object represents the type of a media in a paid message.
 *
 * Source: https://core.telegram.org/bots/api#paidmedia
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum PaidMediaType: string
{
  case Photo = 'photo';
  case Preview = 'preview';
  case Video = 'video';
  case LivePhoto = 'live_photo';
}
