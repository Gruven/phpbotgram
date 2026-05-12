<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes reply parameters for the message that is being sent.
 *
 * Source: https://core.telegram.org/bots/api#replyparameters
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ReplyParameters extends TelegramObject
{
  /**
   * @param list<MessageEntity> $quoteEntities
   */
  public function __construct(
    public readonly int $messageId,
    public readonly null|int|string $chatId = null,
    public readonly ?bool $allowSendingWithoutReply = null,
    public readonly ?string $quote = null,
    public readonly ?string $quoteParseMode = null,
    public readonly ?array $quoteEntities = null,
    public readonly ?int $quotePosition = null,
    public readonly ?int $checklistTaskId = null,
    public readonly ?string $pollOptionId = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
