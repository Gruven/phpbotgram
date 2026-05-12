<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Message;

/**
 * Use this method to set the score of the specified user in a game message. On success, if the message is not an inline message, the Message is returned, otherwise True is returned. Returns an error, if the new score is not greater than the user's current score in the chat and force is False.
 *
 * Source: https://core.telegram.org/bots/api#setgamescore
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<Message>
 */
final class SetGameScore extends TelegramMethod
{
  public const string ApiMethod = 'setGameScore';
  public const string ReturnsType = Message::class;

  public function __construct(
    public readonly int $userId,
    public readonly int $score,
    public readonly ?bool $force = null,
    public readonly ?bool $disableEditMessage = null,
    public readonly ?int $chatId = null,
    public readonly ?int $messageId = null,
    public readonly ?string $inlineMessageId = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
