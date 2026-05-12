<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents a link to an article or web page.
 *
 * Source: https://core.telegram.org/bots/api#inlinequeryresultarticle
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InlineQueryResultArticle extends InlineQueryResult
{
  public function __construct(
    public readonly string $id,
    public readonly string $title,
    public readonly InputMessageContent $inputMessageContent,
    public readonly string $type = 'article',
    public readonly ?InlineKeyboardMarkup $replyMarkup = null,
    public readonly ?string $url = null,
    public readonly ?string $description = null,
    public readonly ?string $thumbnailUrl = null,
    public readonly ?int $thumbnailWidth = null,
    public readonly ?int $thumbnailHeight = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
