<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object describes the origin of a message. It can be one of
 *  - MessageOriginUser
 *  - MessageOriginHiddenUser
 *  - MessageOriginChat
 *  - MessageOriginChannel
 *
 * Source: https://core.telegram.org/bots/api#messageorigin
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
abstract class MessageOrigin extends TelegramObject
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
