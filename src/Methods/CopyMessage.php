<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use DateInterval;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotDefault;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\ForceReply;
use Gruven\PhpBotGram\Types\InlineKeyboardMarkup;
use Gruven\PhpBotGram\Types\MessageEntity;
use Gruven\PhpBotGram\Types\MessageId;
use Gruven\PhpBotGram\Types\ReplyKeyboardMarkup;
use Gruven\PhpBotGram\Types\ReplyKeyboardRemove;
use Gruven\PhpBotGram\Types\ReplyParameters;
use Gruven\PhpBotGram\Types\SuggestedPostParameters;

/**
 * Use this method to copy messages of any kind. Service messages, paid media messages, giveaway messages, giveaway winners messages, and invoice messages can't be copied. A quiz poll can be copied only if the value of the field correct_option_id is known to the bot. The method is analogous to the method forwardMessage, but the copied message doesn't have a link to the original message. Returns the MessageId of the sent message on success.
 *
 * Source: https://core.telegram.org/bots/api#copymessage
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<MessageId>
 */
final class CopyMessage extends TelegramMethod
{
  public const string ApiMethod = 'copyMessage';
  public const string ReturnsType = MessageId::class;

  public function __construct(
    public readonly int|string $chatId,
    public readonly int|string $fromChatId,
    public readonly int $messageId,
    public readonly ?int $messageThreadId = null,
    public readonly ?int $directMessagesTopicId = null,
    public readonly DateInterval|DateTime|int|null $videoStartTimestamp = null,
    public readonly ?string $caption = null,
    public readonly BotDefault|string|null $parseMode = new BotDefault('parse_mode'),
    /** @var list<MessageEntity> */
    public readonly ?array $captionEntities = null,
    public readonly bool|BotDefault|null $showCaptionAboveMedia = new BotDefault('show_caption_above_media'),
    public readonly ?bool $disableNotification = null,
    public readonly bool|BotDefault|null $protectContent = new BotDefault('protect_content'),
    public readonly ?bool $allowPaidBroadcast = null,
    public readonly ?string $messageEffectId = null,
    public readonly ?SuggestedPostParameters $suggestedPostParameters = null,
    public readonly ?ReplyParameters $replyParameters = null,
    public readonly ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove|null $replyMarkup = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
