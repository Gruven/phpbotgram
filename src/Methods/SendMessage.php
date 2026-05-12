<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotDefault;
use Gruven\PhpBotGram\Types\Message;

/**
 * @extends TelegramMethod<Message>
 */
final class SendMessage extends TelegramMethod
{
  public const string ApiMethod = 'sendMessage';
  public const string ReturnsType = Message::class;

  public function __construct(
    public readonly int|string $chatId,
    public readonly string $text,
    public readonly null|BotDefault|string $parseMode = new BotDefault('parse_mode'),
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
