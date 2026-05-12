<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * This object represents input profile photo type
 *
 * Source: https://core.telegram.org/bots/api#storyareatype
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum StoryAreaTypeType: string
{
  case Location = 'location';
  case SuggestedReaction = 'suggested_reaction';
  case Link = 'link';
  case Weather = 'weather';
  case UniqueGift = 'unique_gift';
}
