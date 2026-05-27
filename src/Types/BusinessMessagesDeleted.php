<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object is received when messages are deleted from a connected business account.
 *
 * Source: https://core.telegram.org/bots/api#businessmessagesdeleted
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BusinessMessagesDeleted extends TelegramObject
{
  /**
   * @param list<int> $messageIds
   */
  public function __construct(
    public readonly string $businessConnectionId,
    public readonly Chat $chat,
    public readonly array $messageIds,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
