<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\InlineQueryResult;
use Gruven\PhpBotGram\Types\InlineQueryResultsButton;

/**
 * Use this method to send answers to an inline query. On success, True is returned.
 * No more than 50 results per query are allowed.
 *
 * Source: https://core.telegram.org/bots/api#answerinlinequery
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class AnswerInlineQuery extends TelegramMethod
{
  public const string ApiMethod = 'answerInlineQuery';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $inlineQueryId,
    /** @var list<InlineQueryResult> */
    public readonly array $results,
    public readonly ?int $cacheTime = null,
    public readonly ?bool $isPersonal = null,
    public readonly ?string $nextOffset = null,
    public readonly ?InlineQueryResultsButton $button = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
