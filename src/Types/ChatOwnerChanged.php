<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a service message about an ownership change in the chat.
 *
 * Source: https://core.telegram.org/bots/api#chatownerchanged
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChatOwnerChanged extends TelegramObject
{
  public function __construct(
    public readonly User $newOwner,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
