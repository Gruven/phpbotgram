<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object describes paid media. Currently, it can be one of
 *  - PaidMediaLivePhoto
 *  - PaidMediaPhoto
 *  - PaidMediaPreview
 *  - PaidMediaVideo
 *
 * Source: https://core.telegram.org/bots/api#paidmedia
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
abstract class PaidMedia extends TelegramObject
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
