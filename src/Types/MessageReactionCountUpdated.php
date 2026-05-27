<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * This object represents reaction changes on a message with anonymous reactions.
 *
 * Source: https://core.telegram.org/bots/api#messagereactioncountupdated
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class MessageReactionCountUpdated extends TelegramObject
{
  /**
   * @param list<ReactionCount> $reactions
   */
  public function __construct(
    public readonly Chat $chat,
    public readonly int $messageId,
    public readonly DateTime $date,
    public readonly array $reactions,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
