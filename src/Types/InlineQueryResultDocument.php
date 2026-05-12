<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents a link to a file. By default, this file will be sent by the user with an optional caption. Alternatively, you can use input_message_content to send a message with the specified content instead of the file. Currently, only .PDF and .ZIP files can be sent using this method.
 *
 * Source: https://core.telegram.org/bots/api#inlinequeryresultdocument
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InlineQueryResultDocument extends InlineQueryResult
{
  /**
   * @param list<MessageEntity> $captionEntities
   */
  public function __construct(
    public readonly string $type,
    public readonly string $id,
    public readonly string $title,
    public readonly ?string $caption,
    public readonly ?string $parseMode,
    public readonly ?array $captionEntities,
    public readonly string $documentUrl,
    public readonly string $mimeType,
    public readonly ?string $description = null,
    public readonly ?InlineKeyboardMarkup $replyMarkup = null,
    public readonly ?InputMessageContent $inputMessageContent = null,
    public readonly ?string $thumbnailUrl = null,
    public readonly ?int $thumbnailWidth = null,
    public readonly ?int $thumbnailHeight = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
