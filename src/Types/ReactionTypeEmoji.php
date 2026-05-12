<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * The reaction is based on an emoji.
 *
 * Source: https://core.telegram.org/bots/api#reactiontypeemoji
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ReactionTypeEmoji extends ReactionType
{
  public function __construct(
    public readonly string $emoji,
    public readonly string $type = 'emoji',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
