<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object describes the way a background is filled based on the selected colors. Currently, it can be one of
 *  - BackgroundFillSolid
 *  - BackgroundFillGradient
 *  - BackgroundFillFreeformGradient
 *
 * Source: https://core.telegram.org/bots/api#backgroundfill
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
abstract class BackgroundFill extends TelegramObject
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
