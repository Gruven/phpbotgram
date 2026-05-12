<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\InlineQueryResult;
use Gruven\PhpBotGram\Types\SentGuestMessage;

/**
 * Use this method to reply to a received guest message. On success, a SentGuestMessage object is returned.
 *
 * Source: https://core.telegram.org/bots/api#answerguestquery
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<SentGuestMessage>
 */
final class AnswerGuestQuery extends TelegramMethod
{
  public const string ApiMethod = 'answerGuestQuery';
  public const string ReturnsType = SentGuestMessage::class;

  public function __construct(
    public readonly string $guestQueryId,
    public readonly InlineQueryResult $result,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
