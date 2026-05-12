<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotDefault;

/**
 * Represents a link to an animated GIF file. By default, this animated GIF file will be sent by the user with optional caption. Alternatively, you can use input_message_content to send a message with the specified content instead of the animation.
 *
 * Source: https://core.telegram.org/bots/api#inlinequeryresultgif
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InlineQueryResultGif extends InlineQueryResult
{
  /**
   * @param list<MessageEntity> $captionEntities
   */
  public function __construct(
    public readonly string $id,
    public readonly string $gifUrl,
    public readonly string $thumbnailUrl,
    public readonly string $type = 'gif',
    public readonly ?int $gifWidth = null,
    public readonly ?int $gifHeight = null,
    public readonly ?int $gifDuration = null,
    public readonly ?string $thumbnailMimeType = null,
    public readonly ?string $title = null,
    public readonly ?string $caption = null,
    public readonly null|BotDefault|string $parseMode = new BotDefault('parse_mode'),
    public readonly ?array $captionEntities = null,
    public readonly null|bool|BotDefault $showCaptionAboveMedia = new BotDefault('show_caption_above_media'),
    public readonly ?InlineKeyboardMarkup $replyMarkup = null,
    public readonly ?InputMessageContent $inputMessageContent = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
