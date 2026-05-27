<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * Represents a chat member that was banned in the chat and can't return to the chat or view chat messages.
 *
 * Source: https://core.telegram.org/bots/api#chatmemberbanned
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChatMemberBanned extends ChatMember
{
  public function __construct(
    public readonly User $user,
    public readonly DateTime $untilDate,
    public readonly string $status = 'kicked',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
