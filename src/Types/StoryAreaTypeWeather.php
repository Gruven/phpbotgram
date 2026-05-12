<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a story area containing weather information. Currently, a story can have up to 3 weather areas.
 *
 * Source: https://core.telegram.org/bots/api#storyareatypeweather
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class StoryAreaTypeWeather extends StoryAreaType
{
  public function __construct(
    public readonly float $temperature,
    public readonly string $emoji,
    public readonly int $backgroundColor,
    public readonly string $type = 'weather',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
