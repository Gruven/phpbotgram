<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\InlineKeyboardMarkup;
use Gruven\PhpBotGram\Types\Message;

/**
 * Use this method to edit live location messages. A location can be edited until its live_period expires or editing is explicitly disabled by a call to stopMessageLiveLocation. On success, if the edited message is not an inline message, the edited Message is returned, otherwise True is returned.
 *
 * Source: https://core.telegram.org/bots/api#editmessagelivelocation
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<Message>
 */
final class EditMessageLiveLocation extends TelegramMethod
{
  public const string ApiMethod = 'editMessageLiveLocation';
  public const string ReturnsType = Message::class;

  public function __construct(
    public readonly float $latitude,
    public readonly float $longitude,
    public readonly ?string $businessConnectionId = null,
    public readonly null|int|string $chatId = null,
    public readonly ?int $messageId = null,
    public readonly ?string $inlineMessageId = null,
    public readonly ?int $livePeriod = null,
    public readonly ?float $horizontalAccuracy = null,
    public readonly ?int $heading = null,
    public readonly ?int $proximityAlertRadius = null,
    public readonly ?InlineKeyboardMarkup $replyMarkup = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
