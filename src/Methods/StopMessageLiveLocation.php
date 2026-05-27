<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\InlineKeyboardMarkup;
use Gruven\PhpBotGram\Types\Message;

/**
 * Use this method to stop updating a live location message before live_period expires. On success, if the message is not an inline message, the edited Message is returned, otherwise True is returned.
 *
 * Source: https://core.telegram.org/bots/api#stopmessagelivelocation
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool|Message>
 */
final class StopMessageLiveLocation extends TelegramMethod
{
  public const string ApiMethod = 'stopMessageLiveLocation';
  public const string ReturnsType = 'union:Message|bool';

  public function __construct(
    public readonly ?string $businessConnectionId = null,
    public readonly int|string|null $chatId = null,
    public readonly ?int $messageId = null,
    public readonly ?string $inlineMessageId = null,
    public readonly ?InlineKeyboardMarkup $replyMarkup = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
