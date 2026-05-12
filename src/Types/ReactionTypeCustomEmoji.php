<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * The reaction is based on a custom emoji.
 *
 * Source: https://core.telegram.org/bots/api#reactiontypecustomemoji
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ReactionTypeCustomEmoji extends ReactionType
{
  public function __construct(
    public readonly string $customEmojiId,
    public readonly string $type = 'custom_emoji',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
