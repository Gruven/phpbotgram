<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A block with a music file, corresponding to the HTML tag <audio>.
 *
 * Source: https://core.telegram.org/bots/api#richblockaudio
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichBlockAudio extends RichBlock
{
  public function __construct(
    public readonly Audio $audio,
    public readonly string $type = 'audio',
    public readonly ?RichBlockCaption $caption = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
