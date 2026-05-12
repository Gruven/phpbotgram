<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents a chat member that isn't currently a member of the chat, but may join it themselves.
 *
 * Source: https://core.telegram.org/bots/api#chatmemberleft
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChatMemberLeft extends ChatMember
{
  public function __construct(
    public readonly User $user,
    public readonly string $status = 'left',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
