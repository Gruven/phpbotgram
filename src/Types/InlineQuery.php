<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Methods\AnswerInlineQuery;

/**
 * This object represents an incoming inline query. When the user sends an empty query, your bot could return some default or trending results.
 *
 * Source: https://core.telegram.org/bots/api#inlinequery
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InlineQuery extends TelegramObject
{
  /** @var array<string, string> */
  public const array WireNames = [
    'fromUser' => 'from',
  ];

  public function __construct(
    public readonly string $id,
    public readonly User $fromUser,
    public readonly string $query,
    public readonly string $offset,
    public readonly ?string $chatType = null,
    public readonly ?Location $location = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }

  /**
   * @param list<InlineQueryResult> $results
   */
  public function answer(
    array $results,
    ?int $cacheTime = null,
    ?bool $isPersonal = null,
    ?string $nextOffset = null,
    ?InlineQueryResultsButton $button = null,
  ): AnswerInlineQuery {
    return new AnswerInlineQuery(
      inlineQueryId: $this->id,
      results: $results,
      cacheTime: $cacheTime,
      isPersonal: $isPersonal,
      nextOffset: $nextOffset,
      button: $button,
      bot: $this->bot,
    );
  }
}
