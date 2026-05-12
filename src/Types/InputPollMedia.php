<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents the content of a poll description or a quiz explanation to be sent. It should be one of
 *  - InputMediaAnimation
 *  - InputMediaAudio
 *  - InputMediaDocument
 *  - InputMediaLivePhoto
 *  - InputMediaLocation
 *  - InputMediaPhoto
 *  - InputMediaVenue
 *  - InputMediaVideo
 *
 * Source: https://core.telegram.org/bots/api#inputpollmedia
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
abstract class InputPollMedia extends MutableTelegramObject
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
