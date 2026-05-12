<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotDefault;
use Gruven\PhpBotGram\Types\InlineKeyboardMarkup;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\MessageEntity;

/**
 * Use this method to edit captions of messages. On success, if the edited message is not an inline message, the edited Message is returned, otherwise True is returned. Note that business messages that were not sent by the bot and do not contain an inline keyboard can only be edited within 48 hours from the time they were sent.
 *
 * Source: https://core.telegram.org/bots/api#editmessagecaption
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool|Message>
 */
final class EditMessageCaption extends TelegramMethod
{
  public const string ApiMethod = 'editMessageCaption';
  public const string ReturnsType = 'union:Message|bool';

  public function __construct(
    public readonly ?string $businessConnectionId = null,
    public readonly null|int|string $chatId = null,
    public readonly ?int $messageId = null,
    public readonly ?string $inlineMessageId = null,
    public readonly ?string $caption = null,
    public readonly null|BotDefault|string $parseMode = new BotDefault('parse_mode'),
    /** @var list<MessageEntity> */
    public readonly ?array $captionEntities = null,
    public readonly null|bool|BotDefault $showCaptionAboveMedia = new BotDefault('show_caption_above_media'),
    public readonly ?InlineKeyboardMarkup $replyMarkup = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
