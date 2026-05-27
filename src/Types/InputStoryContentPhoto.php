<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a photo to post as a story.
 *
 * Source: https://core.telegram.org/bots/api#inputstorycontentphoto
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputStoryContentPhoto extends InputStoryContent
{
  public function __construct(
    public readonly string $photo,
    public readonly string $type = 'photo',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
