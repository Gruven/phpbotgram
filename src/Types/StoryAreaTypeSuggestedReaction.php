<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a story area pointing to a suggested reaction. Currently, a story can have up to 5 suggested reaction areas.
 *
 * Source: https://core.telegram.org/bots/api#storyareatypesuggestedreaction
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class StoryAreaTypeSuggestedReaction extends StoryAreaType
{
  public function __construct(
    public readonly string $type,
    public readonly ReactionType $reactionType,
    public readonly ?bool $isDark = null,
    public readonly ?bool $isFlipped = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
