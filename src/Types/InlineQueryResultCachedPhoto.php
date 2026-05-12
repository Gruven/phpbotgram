<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents a link to a photo stored on the Telegram servers. By default, this photo will be sent by the user with an optional caption. Alternatively, you can use input_message_content to send a message with the specified content instead of the photo.
 *
 * Source: https://core.telegram.org/bots/api#inlinequeryresultcachedphoto
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InlineQueryResultCachedPhoto extends InlineQueryResult
{
  /**
   * @param list<MessageEntity> $captionEntities
   */
  public function __construct(
    public readonly string $id,
    public readonly string $photoFileId,
    public readonly string $type = 'photo',
    public readonly ?string $title = null,
    public readonly ?string $description = null,
    public readonly ?string $caption = null,
    public readonly ?string $parseMode = null,
    public readonly ?array $captionEntities = null,
    public readonly ?bool $showCaptionAboveMedia = null,
    public readonly ?InlineKeyboardMarkup $replyMarkup = null,
    public readonly ?InputMessageContent $inputMessageContent = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
