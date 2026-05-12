<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents a link to a sticker stored on the Telegram servers. By default, this sticker will be sent by the user. Alternatively, you can use input_message_content to send a message with the specified content instead of the sticker.
 *
 * Source: https://core.telegram.org/bots/api#inlinequeryresultcachedsticker
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InlineQueryResultCachedSticker extends InlineQueryResult
{
  public function __construct(
    public readonly string $id,
    public readonly string $stickerFileId,
    public readonly string $type = 'sticker',
    public readonly ?InlineKeyboardMarkup $replyMarkup = null,
    public readonly ?InputMessageContent $inputMessageContent = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
