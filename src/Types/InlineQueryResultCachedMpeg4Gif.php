<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotDefault;

/**
 * Represents a link to a video animation (H.264/MPEG-4 AVC video without sound) stored on the Telegram servers. By default, this animated MPEG-4 file will be sent by the user with an optional caption. Alternatively, you can use input_message_content to send a message with the specified content instead of the animation.
 *
 * Source: https://core.telegram.org/bots/api#inlinequeryresultcachedmpeg4gif
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InlineQueryResultCachedMpeg4Gif extends InlineQueryResult
{
  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function __construct(
    public readonly string $id,
    public readonly string $mpeg4FileId,
    public readonly string $type = 'mpeg4_gif',
    public readonly ?string $title = null,
    public readonly ?string $caption = null,
    public readonly BotDefault|string|null $parseMode = new BotDefault('parse_mode'),
    public readonly ?array $captionEntities = null,
    public readonly bool|BotDefault|null $showCaptionAboveMedia = new BotDefault('show_caption_above_media'),
    public readonly ?InlineKeyboardMarkup $replyMarkup = null,
    public readonly ?InputMessageContent $inputMessageContent = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
