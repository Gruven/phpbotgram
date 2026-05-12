<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotDefault;

/**
 * Represents a link to a page containing an embedded video player or a video file. By default, this video file will be sent by the user with an optional caption. Alternatively, you can use input_message_content to send a message with the specified content instead of the video.
 * If an InlineQueryResultVideo message contains an embedded video (e.g., YouTube), you must replace its content using input_message_content.
 *
 * Source: https://core.telegram.org/bots/api#inlinequeryresultvideo
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InlineQueryResultVideo extends InlineQueryResult
{
  /**
   * @param list<MessageEntity> $captionEntities
   */
  public function __construct(
    public readonly string $id,
    public readonly string $videoUrl,
    public readonly string $mimeType,
    public readonly string $thumbnailUrl,
    public readonly string $title,
    public readonly string $type = 'video',
    public readonly ?string $caption = null,
    public readonly null|BotDefault|string $parseMode = new BotDefault('parse_mode'),
    public readonly ?array $captionEntities = null,
    public readonly null|bool|BotDefault $showCaptionAboveMedia = new BotDefault('show_caption_above_media'),
    public readonly ?int $videoWidth = null,
    public readonly ?int $videoHeight = null,
    public readonly ?int $videoDuration = null,
    public readonly ?string $description = null,
    public readonly ?InlineKeyboardMarkup $replyMarkup = null,
    public readonly ?InputMessageContent $inputMessageContent = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
