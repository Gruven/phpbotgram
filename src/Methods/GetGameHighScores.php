<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to get data for high score tables. Will return the score of the specified user and several of their neighbors in a game. Returns an Array of GameHighScore objects.
 * This method will currently return scores for the target user, plus two of their closest neighbors on each side. Will also return the top three users if the user and their neighbors are not among them. Please note that this behavior is subject to change.
 *
 * Source: https://core.telegram.org/bots/api#getgamehighscores
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class GetGameHighScores extends TelegramMethod
{
  public const string ApiMethod = 'getGameHighScores';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int $userId,
    public readonly ?int $chatId = null,
    public readonly ?int $messageId = null,
    public readonly ?string $inlineMessageId = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
