<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents a reaction added to a message along with the number of times it was added.
 *
 * Source: https://core.telegram.org/bots/api#reactioncount
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ReactionCount extends TelegramObject
{
  public function __construct(
    public readonly ReactionType $type,
    public readonly int $totalCount,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
