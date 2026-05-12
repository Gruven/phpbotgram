<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents a link to a voice message stored on the Telegram servers. By default, this voice message will be sent by the user. Alternatively, you can use input_message_content to send a message with the specified content instead of the voice message.
 *
 * Source: https://core.telegram.org/bots/api#inlinequeryresultcachedvoice
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InlineQueryResultCachedVoice extends InlineQueryResult
{
  /**
   * @param list<MessageEntity> $captionEntities
   */
  public function __construct(
    public readonly string $id,
    public readonly string $voiceFileId,
    public readonly string $title,
    public readonly string $type = 'voice',
    public readonly ?string $caption = null,
    public readonly ?string $parseMode = null,
    public readonly ?array $captionEntities = null,
    public readonly ?InlineKeyboardMarkup $replyMarkup = null,
    public readonly ?InputMessageContent $inputMessageContent = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
