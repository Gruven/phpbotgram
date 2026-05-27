<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a clickable area on a story media.
 *
 * Source: https://core.telegram.org/bots/api#storyarea
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class StoryArea extends TelegramObject
{
  public function __construct(
    public readonly StoryAreaPosition $position,
    public readonly StoryAreaType $type,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
