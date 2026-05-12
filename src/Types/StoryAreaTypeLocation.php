<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a story area pointing to a location. Currently, a story can have up to 10 location areas.
 *
 * Source: https://core.telegram.org/bots/api#storyareatypelocation
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class StoryAreaTypeLocation extends StoryAreaType
{
  public function __construct(
    public readonly string $type,
    public readonly float $latitude,
    public readonly float $longitude,
    public readonly ?LocationAddress $address = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
