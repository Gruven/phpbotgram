<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a service message about a change in auto-delete timer settings.
 *
 * Source: https://core.telegram.org/bots/api#messageautodeletetimerchanged
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class MessageAutoDeleteTimerChanged extends TelegramObject
{
  public function __construct(
    public readonly int $messageAutoDeleteTime,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
