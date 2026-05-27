<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object describes the type of a background. Currently, it can be one of
 *  - BackgroundTypeFill
 *  - BackgroundTypeWallpaper
 *  - BackgroundTypePattern
 *  - BackgroundTypeChatTheme
 *
 * Source: https://core.telegram.org/bots/api#backgroundtype
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
abstract class BackgroundType extends TelegramObject
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
