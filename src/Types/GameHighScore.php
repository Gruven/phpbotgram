<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents one row of the high scores table for a game.
 * And that's about all we've got for now.
 * If you've got any questions, please check out our Bot FAQ
 *
 * Source: https://core.telegram.org/bots/api#gamehighscore
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class GameHighScore extends TelegramObject
{
  public function __construct(
    public readonly int $position,
    public readonly User $user,
    public readonly int $score,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
