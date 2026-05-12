<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a service message about the chat owner leaving the chat.
 *
 * Source: https://core.telegram.org/bots/api#chatownerleft
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChatOwnerLeft extends TelegramObject
{
  public function __construct(
    public readonly ?User $newOwner = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
