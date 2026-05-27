<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes the type of a clickable area on a story. Currently, it can be one of
 *  - StoryAreaTypeLocation
 *  - StoryAreaTypeSuggestedReaction
 *  - StoryAreaTypeLink
 *  - StoryAreaTypeWeather
 *  - StoryAreaTypeUniqueGift
 *
 * Source: https://core.telegram.org/bots/api#storyareatype
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
abstract class StoryAreaType extends TelegramObject
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
