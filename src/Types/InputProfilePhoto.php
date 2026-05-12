<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object describes a profile photo to set. Currently, it can be one of
 *  - InputProfilePhotoStatic
 *  - InputProfilePhotoAnimated
 *
 * Source: https://core.telegram.org/bots/api#inputprofilephoto
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
abstract class InputProfilePhoto extends TelegramObject
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
