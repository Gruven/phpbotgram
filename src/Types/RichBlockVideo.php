<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A block with a video, corresponding to the HTML tag <video>.
 *
 * Source: https://core.telegram.org/bots/api#richblockvideo
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichBlockVideo extends RichBlock
{
  public function __construct(
    public readonly Video $video,
    public readonly string $type = 'video',
    public readonly ?bool $hasSpoiler = null,
    public readonly ?RichBlockCaption $caption = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
