<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a story area pointing to a unique gift. Currently, a story can have at most 1 unique gift area.
 *
 * Source: https://core.telegram.org/bots/api#storyareatypeuniquegift
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class StoryAreaTypeUniqueGift extends StoryAreaType
{
  public function __construct(
    public readonly string $name,
    public readonly string $type = 'unique_gift',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
