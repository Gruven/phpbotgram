<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use DateInterval;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotDefault;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\ForceReply;
use Gruven\PhpBotGram\Types\InlineKeyboardMarkup;
use Gruven\PhpBotGram\Types\InputFile;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\MessageEntity;
use Gruven\PhpBotGram\Types\ReplyKeyboardMarkup;
use Gruven\PhpBotGram\Types\ReplyKeyboardRemove;
use Gruven\PhpBotGram\Types\ReplyParameters;
use Gruven\PhpBotGram\Types\SuggestedPostParameters;

/**
 * Use this method to send video files, Telegram clients support MPEG4 videos (other formats may be sent as Document). On success, the sent Message is returned. Bots can currently send video files of up to 50 MB in size, this limit may be changed in the future.
 *
 * Source: https://core.telegram.org/bots/api#sendvideo
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<Message>
 */
final class SendVideo extends TelegramMethod
{
  public const string ApiMethod = 'sendVideo';
  public const string ReturnsType = Message::class;

  public function __construct(
    public readonly int|string $chatId,
    public readonly InputFile|string $video,
    public readonly ?string $businessConnectionId = null,
    public readonly ?int $messageThreadId = null,
    public readonly ?int $directMessagesTopicId = null,
    public readonly ?int $duration = null,
    public readonly ?int $width = null,
    public readonly ?int $height = null,
    public readonly ?InputFile $thumbnail = null,
    public readonly null|InputFile|string $cover = null,
    public readonly null|DateInterval|DateTime|int $startTimestamp = null,
    public readonly ?string $caption = null,
    public readonly null|BotDefault|string $parseMode = new BotDefault('parse_mode'),
    /** @var list<MessageEntity> */
    public readonly ?array $captionEntities = null,
    public readonly null|bool|BotDefault $showCaptionAboveMedia = new BotDefault('show_caption_above_media'),
    public readonly ?bool $hasSpoiler = null,
    public readonly ?bool $supportsStreaming = null,
    public readonly ?bool $disableNotification = null,
    public readonly null|bool|BotDefault $protectContent = new BotDefault('protect_content'),
    public readonly ?bool $allowPaidBroadcast = null,
    public readonly ?string $messageEffectId = null,
    public readonly ?SuggestedPostParameters $suggestedPostParameters = null,
    public readonly ?ReplyParameters $replyParameters = null,
    public readonly null|ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove $replyMarkup = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
