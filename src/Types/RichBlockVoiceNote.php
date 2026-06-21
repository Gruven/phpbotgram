<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A block with a voice note, corresponding to the HTML tag <audio>.
 *
 * Source: https://core.telegram.org/bots/api#richblockvoicenote
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichBlockVoiceNote extends RichBlock
{
  public function __construct(
    public readonly Voice $voiceNote,
    public readonly string $type = 'voice_note',
    public readonly ?RichBlockCaption $caption = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
