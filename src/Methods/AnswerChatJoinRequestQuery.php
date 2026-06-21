<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to process a received chat join request query. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#answerchatjoinrequestquery
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class AnswerChatJoinRequestQuery extends TelegramMethod
{
  public const string ApiMethod = 'answerChatJoinRequestQuery';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $chatJoinRequestQueryId,
    public readonly string $result,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
