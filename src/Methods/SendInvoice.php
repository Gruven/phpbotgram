<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotDefault;
use Gruven\PhpBotGram\Types\InlineKeyboardMarkup;
use Gruven\PhpBotGram\Types\LabeledPrice;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\ReplyParameters;
use Gruven\PhpBotGram\Types\SuggestedPostParameters;

/**
 * Use this method to send invoices. On success, the sent Message is returned.
 *
 * Source: https://core.telegram.org/bots/api#sendinvoice
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<Message>
 */
final class SendInvoice extends TelegramMethod
{
  public const string ApiMethod = 'sendInvoice';
  public const string ReturnsType = Message::class;

  public function __construct(
    public readonly int|string $chatId,
    public readonly string $title,
    public readonly string $description,
    public readonly string $payload,
    public readonly string $currency,
    /** @var list<LabeledPrice> */
    public readonly array $prices,
    public readonly ?int $messageThreadId = null,
    public readonly ?int $directMessagesTopicId = null,
    public readonly ?string $providerToken = null,
    public readonly ?int $maxTipAmount = null,
    /** @var list<int> */
    public readonly ?array $suggestedTipAmounts = null,
    public readonly ?string $startParameter = null,
    public readonly ?string $providerData = null,
    public readonly ?string $photoUrl = null,
    public readonly ?int $photoSize = null,
    public readonly ?int $photoWidth = null,
    public readonly ?int $photoHeight = null,
    public readonly ?bool $needName = null,
    public readonly ?bool $needPhoneNumber = null,
    public readonly ?bool $needEmail = null,
    public readonly ?bool $needShippingAddress = null,
    public readonly ?bool $sendPhoneNumberToProvider = null,
    public readonly ?bool $sendEmailToProvider = null,
    public readonly ?bool $isFlexible = null,
    public readonly ?bool $disableNotification = null,
    public readonly null|bool|BotDefault $protectContent = new BotDefault('protect_content'),
    public readonly ?bool $allowPaidBroadcast = null,
    public readonly ?string $messageEffectId = null,
    public readonly ?SuggestedPostParameters $suggestedPostParameters = null,
    public readonly ?ReplyParameters $replyParameters = null,
    public readonly ?InlineKeyboardMarkup $replyMarkup = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
