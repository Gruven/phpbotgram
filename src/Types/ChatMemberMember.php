<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * Represents a chat member that has no additional privileges or restrictions.
 *
 * Source: https://core.telegram.org/bots/api#chatmembermember
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChatMemberMember extends ChatMember
{
  public function __construct(
    public readonly string $status,
    public readonly ?string $tag,
    public readonly User $user,
    public readonly ?DateTime $untilDate = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
