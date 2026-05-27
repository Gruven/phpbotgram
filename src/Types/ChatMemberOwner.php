<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents a chat member that owns the chat and has all administrator privileges.
 *
 * Source: https://core.telegram.org/bots/api#chatmemberowner
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChatMemberOwner extends ChatMember
{
  public function __construct(
    public readonly User $user,
    public readonly bool $isAnonymous,
    public readonly string $status = 'creator',
    public readonly ?string $customTitle = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
