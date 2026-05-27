<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotDefault;
use Gruven\PhpBotGram\Types\ForceReply;
use Gruven\PhpBotGram\Types\InlineKeyboardMarkup;
use Gruven\PhpBotGram\Types\LinkPreviewOptions;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\MessageEntity;
use Gruven\PhpBotGram\Types\ReplyKeyboardMarkup;
use Gruven\PhpBotGram\Types\ReplyKeyboardRemove;
use Gruven\PhpBotGram\Types\ReplyParameters;
use Gruven\PhpBotGram\Types\SuggestedPostParameters;

/**
 * Use this method to send text messages. On success, the sent Message is returned.
 *
 * Source: https://core.telegram.org/bots/api#sendmessage
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<Message>
 */
final class SendMessage extends TelegramMethod
{
  public const string ApiMethod = 'sendMessage';
  public const string ReturnsType = Message::class;

  public function __construct(
    public readonly int|string $chatId,
    public readonly string $text,
    public readonly ?string $businessConnectionId = null,
    public readonly ?int $messageThreadId = null,
    public readonly ?int $directMessagesTopicId = null,
    public readonly BotDefault|string|null $parseMode = new BotDefault('parse_mode'),
    /** @var list<MessageEntity> */
    public readonly ?array $entities = null,
    public readonly BotDefault|LinkPreviewOptions|null $linkPreviewOptions = new BotDefault('link_preview'),
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
