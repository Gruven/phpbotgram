<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object describes the type of a reaction. Currently, it can be one of
 *  - ReactionTypeEmoji
 *  - ReactionTypeCustomEmoji
 *  - ReactionTypePaid
 *
 * Source: https://core.telegram.org/bots/api#reactiontype
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
abstract class ReactionType extends TelegramObject
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
