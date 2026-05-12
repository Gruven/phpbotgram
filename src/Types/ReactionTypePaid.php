<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * The reaction is paid.
 *
 * Source: https://core.telegram.org/bots/api#reactiontypepaid
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ReactionTypePaid extends ReactionType
{
  public function __construct(
    public readonly string $type = 'paid',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
