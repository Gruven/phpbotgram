<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * This object represents reaction type.
 *
 * Source: https://core.telegram.org/bots/api#reactiontype
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum ReactionTypeType: string
{
  case Emoji = 'emoji';
  case CustomEmoji = 'custom_emoji';
  case Paid = 'paid';
}
