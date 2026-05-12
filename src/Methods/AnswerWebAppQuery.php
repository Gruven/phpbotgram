<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\InlineQueryResult;
use Gruven\PhpBotGram\Types\SentWebAppMessage;

/**
 * Use this method to set the result of an interaction with a Web App and send a corresponding message on behalf of the user to the chat from which the query originated. On success, a SentWebAppMessage object is returned.
 *
 * Source: https://core.telegram.org/bots/api#answerwebappquery
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<SentWebAppMessage>
 */
final class AnswerWebAppQuery extends TelegramMethod
{
  public const string ApiMethod = 'answerWebAppQuery';
  public const string ReturnsType = SentWebAppMessage::class;

  public function __construct(
    public readonly string $webAppQueryId,
    public readonly InlineQueryResult $result,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
