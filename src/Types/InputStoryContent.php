<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object describes the content of a story to post. Currently, it can be one of
 *  - InputStoryContentPhoto
 *  - InputStoryContentVideo
 *
 * Source: https://core.telegram.org/bots/api#inputstorycontent
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
abstract class InputStoryContent extends TelegramObject
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
