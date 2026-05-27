<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotDefault;

/**
 * Represents a link to a voice recording in an .OGG container encoded with OPUS. By default, this voice recording will be sent by the user. Alternatively, you can use input_message_content to send a message with the specified content instead of the the voice message.
 *
 * Source: https://core.telegram.org/bots/api#inlinequeryresultvoice
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InlineQueryResultVoice extends InlineQueryResult
{
  /**
   * @param list<MessageEntity> $captionEntities
   */
  public function __construct(
    public readonly string $id,
    public readonly string $voiceUrl,
    public readonly string $title,
    public readonly string $type = 'voice',
    public readonly ?string $caption = null,
    public readonly BotDefault|string|null $parseMode = new BotDefault('parse_mode'),
    public readonly ?array $captionEntities = null,
    public readonly ?int $voiceDuration = null,
    public readonly ?InlineKeyboardMarkup $replyMarkup = null,
    public readonly ?InputMessageContent $inputMessageContent = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
