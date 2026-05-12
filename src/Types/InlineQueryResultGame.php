<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents a Game.
 *
 * Source: https://core.telegram.org/bots/api#inlinequeryresultgame
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InlineQueryResultGame extends InlineQueryResult
{
  public function __construct(
    public readonly string $id,
    public readonly string $gameShortName,
    public readonly string $type = 'game',
    public readonly ?InlineKeyboardMarkup $replyMarkup = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
