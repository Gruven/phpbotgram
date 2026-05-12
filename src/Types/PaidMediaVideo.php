<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * The paid media is a video.
 *
 * Source: https://core.telegram.org/bots/api#paidmediavideo
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PaidMediaVideo extends PaidMedia
{
  public function __construct(
    public readonly Video $video,
    public readonly string $type = 'video',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
