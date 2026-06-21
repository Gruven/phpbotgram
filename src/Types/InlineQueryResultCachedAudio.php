<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotDefault;

/**
 * Represents a link to an MP3 audio file stored on the Telegram servers. By default, this audio file will be sent by the user. Alternatively, you can use input_message_content to send a message with the specified content instead of the audio.
 *
 * Source: https://core.telegram.org/bots/api#inlinequeryresultcachedaudio
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InlineQueryResultCachedAudio extends InlineQueryResult
{
  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function __construct(
    public readonly string $id,
    public readonly string $audioFileId,
    public readonly string $type = 'audio',
    public readonly ?string $caption = null,
    public readonly BotDefault|string|null $parseMode = new BotDefault('parse_mode'),
    public readonly ?array $captionEntities = null,
    public readonly ?InlineKeyboardMarkup $replyMarkup = null,
    public readonly ?InputMessageContent $inputMessageContent = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
