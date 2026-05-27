<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotDefault;
use Gruven\PhpBotGram\Types\InlineKeyboardMarkup;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\ReplyParameters;

/**
 * Use this method to send a game. On success, the sent Message is returned.
 *
 * Source: https://core.telegram.org/bots/api#sendgame
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<Message>
 */
final class SendGame extends TelegramMethod
{
  public const string ApiMethod = 'sendGame';
  public const string ReturnsType = Message::class;

  public function __construct(
    public readonly int|string $chatId,
    public readonly string $gameShortName,
    public readonly ?string $businessConnectionId = null,
    public readonly ?int $messageThreadId = null,
    public readonly ?bool $disableNotification = null,
    public readonly bool|BotDefault|null $protectContent = new BotDefault('protect_content'),
    public readonly ?bool $allowPaidBroadcast = null,
    public readonly ?string $messageEffectId = null,
    public readonly ?ReplyParameters $replyParameters = null,
    public readonly ?InlineKeyboardMarkup $replyMarkup = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
