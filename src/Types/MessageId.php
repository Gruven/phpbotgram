<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a unique message identifier.
 *
 * Source: https://core.telegram.org/bots/api#messageid
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class MessageId extends TelegramObject
{
  public function __construct(
    public readonly int $messageId,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
