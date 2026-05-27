<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * This object represents input story content photo type.
 *
 * Source: https://core.telegram.org/bots/api#inputstorycontentphoto
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum InputStoryContentType: string
{
  case Photo = 'photo';
  case Video = 'video';
}
