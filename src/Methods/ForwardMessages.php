<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\MessageId;

/**
 * Use this method to forward multiple messages of any kind. If some of the specified messages can't be found or forwarded, they are skipped. Service messages and messages with protected content can't be forwarded. Album grouping is kept for forwarded messages. On success, an array of MessageId of the sent messages is returned.
 *
 * Source: https://core.telegram.org/bots/api#forwardmessages
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<list<MessageId>>
 */
final class ForwardMessages extends TelegramMethod
{
  public const string ApiMethod = 'forwardMessages';
  public const string ReturnsType = 'list:MessageId';

  public function __construct(
    public readonly int|string $chatId,
    public readonly int|string $fromChatId,
    /** @var list<int> */
    public readonly array $messageIds,
    public readonly ?int $messageThreadId = null,
    public readonly ?int $directMessagesTopicId = null,
    public readonly ?bool $disableNotification = null,
    public readonly ?bool $protectContent = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
