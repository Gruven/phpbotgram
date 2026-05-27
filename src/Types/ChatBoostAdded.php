<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a service message about a user boosting a chat.
 *
 * Source: https://core.telegram.org/bots/api#chatboostadded
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChatBoostAdded extends TelegramObject
{
  public function __construct(
    public readonly int $boostCount,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
