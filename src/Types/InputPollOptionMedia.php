<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents the content of a poll option to be sent. It should be one of
 *  - InputMediaAnimation
 *  - InputMediaLivePhoto
 *  - InputMediaLocation
 *  - InputMediaPhoto
 *  - InputMediaSticker
 *  - InputMediaVenue
 *  - InputMediaVideo
 *
 * Source: https://core.telegram.org/bots/api#inputpolloptionmedia
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
abstract class InputPollOptionMedia extends MutableTelegramObject implements InputPollOptionMediaInterface
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
