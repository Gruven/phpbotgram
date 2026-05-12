<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use DateInterval;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotDefault;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\ForceReply;
use Gruven\PhpBotGram\Types\InlineKeyboardMarkup;
use Gruven\PhpBotGram\Types\InputPollMediaInterface;
use Gruven\PhpBotGram\Types\InputPollOption;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\MessageEntity;
use Gruven\PhpBotGram\Types\ReplyKeyboardMarkup;
use Gruven\PhpBotGram\Types\ReplyKeyboardRemove;
use Gruven\PhpBotGram\Types\ReplyParameters;

/**
 * Use this method to send a native poll. On success, the sent Message is returned.
 *
 * Source: https://core.telegram.org/bots/api#sendpoll
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<Message>
 */
final class SendPoll extends TelegramMethod
{
  public const string ApiMethod = 'sendPoll';
  public const string ReturnsType = Message::class;

  public function __construct(
    public readonly int|string $chatId,
    public readonly string $question,
    /** @var list<InputPollOption|string> */
    public readonly array $options,
    public readonly ?string $businessConnectionId = null,
    public readonly ?int $messageThreadId = null,
    public readonly null|BotDefault|string $questionParseMode = new BotDefault('parse_mode'),
    /** @var list<MessageEntity> */
    public readonly ?array $questionEntities = null,
    public readonly ?bool $isAnonymous = null,
    public readonly ?string $type = null,
    public readonly ?bool $allowsMultipleAnswers = null,
    public readonly ?bool $allowsRevoting = null,
    public readonly ?bool $shuffleOptions = null,
    public readonly ?bool $allowAddingOptions = null,
    public readonly ?bool $hideResultsUntilCloses = null,
    public readonly ?bool $membersOnly = null,
    /** @var list<string> */
    public readonly ?array $countryCodes = null,
    /** @var list<int> */
    public readonly ?array $correctOptionIds = null,
    public readonly ?string $explanation = null,
    public readonly null|BotDefault|string $explanationParseMode = new BotDefault('parse_mode'),
    /** @var list<MessageEntity> */
    public readonly ?array $explanationEntities = null,
    public readonly ?InputPollMediaInterface $explanationMedia = null,
    public readonly ?int $openPeriod = null,
    public readonly null|DateInterval|DateTime|int $closeDate = null,
    public readonly ?bool $isClosed = null,
    public readonly ?string $description = null,
    public readonly null|BotDefault|string $descriptionParseMode = new BotDefault('parse_mode'),
    /** @var list<MessageEntity> */
    public readonly ?array $descriptionEntities = null,
    public readonly ?InputPollMediaInterface $media = null,
    public readonly ?bool $disableNotification = null,
    public readonly null|bool|BotDefault $protectContent = new BotDefault('protect_content'),
    public readonly ?bool $allowPaidBroadcast = null,
    public readonly ?string $messageEffectId = null,
    public readonly ?ReplyParameters $replyParameters = null,
    public readonly null|ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove $replyMarkup = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
