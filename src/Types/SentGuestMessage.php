<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes an inline message sent by a guest bot.
 *
 * Source: https://core.telegram.org/bots/api#sentguestmessage
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class SentGuestMessage extends TelegramObject
{
  public function __construct(
    public readonly string $inlineMessageId,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
