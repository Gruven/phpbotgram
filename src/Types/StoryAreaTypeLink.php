<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a story area pointing to an HTTP or tg:// link. Currently, a story can have up to 3 link areas.
 *
 * Source: https://core.telegram.org/bots/api#storyareatypelink
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class StoryAreaTypeLink extends StoryAreaType
{
  public function __construct(
    public readonly string $type,
    public readonly string $url,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
