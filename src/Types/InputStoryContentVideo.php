<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a video to post as a story.
 *
 * Source: https://core.telegram.org/bots/api#inputstorycontentvideo
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputStoryContentVideo extends InputStoryContent
{
  public function __construct(
    public readonly string $video,
    public readonly string $type = 'video',
    public readonly ?float $duration = null,
    public readonly ?float $coverFrameTimestamp = null,
    public readonly ?bool $isAnimation = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
