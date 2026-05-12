<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents a link to an MP3 audio file. By default, this audio file will be sent by the user. Alternatively, you can use input_message_content to send a message with the specified content instead of the audio.
 *
 * Source: https://core.telegram.org/bots/api#inlinequeryresultaudio
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InlineQueryResultAudio extends InlineQueryResult
{
  /**
   * @param list<MessageEntity> $captionEntities
   */
  public function __construct(
    public readonly string $id,
    public readonly string $audioUrl,
    public readonly string $title,
    public readonly string $type = 'audio',
    public readonly ?string $caption = null,
    public readonly ?string $parseMode = null,
    public readonly ?array $captionEntities = null,
    public readonly ?string $performer = null,
    public readonly ?int $audioDuration = null,
    public readonly ?InlineKeyboardMarkup $replyMarkup = null,
    public readonly ?InputMessageContent $inputMessageContent = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
