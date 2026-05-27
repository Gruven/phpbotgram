<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\InlineKeyboardMarkup;
use Gruven\PhpBotGram\Types\Poll;

/**
 * Use this method to stop a poll which was sent by the bot. On success, the stopped Poll is returned.
 *
 * Source: https://core.telegram.org/bots/api#stoppoll
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<Poll>
 */
final class StopPoll extends TelegramMethod
{
  public const string ApiMethod = 'stopPoll';
  public const string ReturnsType = Poll::class;

  public function __construct(
    public readonly int|string $chatId,
    public readonly int $messageId,
    public readonly ?string $businessConnectionId = null,
    public readonly ?InlineKeyboardMarkup $replyMarkup = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
