<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object describes the paid media to be sent. Currently, it can be one of
 *  - InputPaidMediaLivePhoto
 *  - InputPaidMediaPhoto
 *  - InputPaidMediaVideo
 *
 * Source: https://core.telegram.org/bots/api#inputpaidmedia
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
abstract class InputPaidMedia extends TelegramObject
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
