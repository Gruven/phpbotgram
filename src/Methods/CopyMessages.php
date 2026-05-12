<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\MessageId;

/**
 * Use this method to copy messages of any kind. If some of the specified messages can't be found or copied, they are skipped. Service messages, paid media messages, giveaway messages, giveaway winners messages, and invoice messages can't be copied. A quiz poll can be copied only if the value of the field correct_option_id is known to the bot. The method is analogous to the method forwardMessages, but the copied messages don't have a link to the original message. Album grouping is kept for copied messages. On success, an array of MessageId of the sent messages is returned.
 *
 * Source: https://core.telegram.org/bots/api#copymessages
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<list<MessageId>>
 */
final class CopyMessages extends TelegramMethod
{
  public const string ApiMethod = 'copyMessages';
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
    public readonly ?bool $removeCaption = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
