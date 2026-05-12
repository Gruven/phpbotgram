<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\InlineKeyboardMarkup;
use Gruven\PhpBotGram\Types\InputMedia;
use Gruven\PhpBotGram\Types\Message;

/**
 * Use this method to edit animation, audio, document, live photo, photo, or video messages, or to add media to text messages. If a message is part of a message album, then it can be edited only to an audio for audio albums, only to a document for document albums and to a photo, a live photo, or a video otherwise. When an inline message is edited, a new file can't be uploaded; use a previously uploaded file via its file_id or specify a URL. On success, if the edited message is not an inline message, the edited Message is returned, otherwise True is returned. Note that business messages that were not sent by the bot and do not contain an inline keyboard can only be edited within 48 hours from the time they were sent.
 *
 * Source: https://core.telegram.org/bots/api#editmessagemedia
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool|Message>
 */
final class EditMessageMedia extends TelegramMethod
{
  public const string ApiMethod = 'editMessageMedia';
  public const string ReturnsType = 'union:Message|bool';

  public function __construct(
    public readonly InputMedia $media,
    public readonly ?string $businessConnectionId = null,
    public readonly null|int|string $chatId = null,
    public readonly ?int $messageId = null,
    public readonly ?string $inlineMessageId = null,
    public readonly ?InlineKeyboardMarkup $replyMarkup = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
