<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object describes a message that can be inaccessible to the bot. It can be one of
 *  - Message
 *  - InaccessibleMessage
 *
 * Source: https://core.telegram.org/bots/api#maybeinaccessiblemessage
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
abstract class MaybeInaccessibleMessage extends TelegramObject
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
