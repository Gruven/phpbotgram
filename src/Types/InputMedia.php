<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents the content of a media message to be sent. It should be one of
 *  - InputMediaAnimation
 *  - InputMediaAudio
 *  - InputMediaDocument
 *  - InputMediaLivePhoto
 *  - InputMediaPhoto
 *  - InputMediaVideo
 *
 * Source: https://core.telegram.org/bots/api#inputmedia
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
abstract class InputMedia extends MutableTelegramObject
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
