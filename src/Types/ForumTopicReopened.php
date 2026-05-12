<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a service message about a forum topic reopened in the chat. Currently holds no information.
 *
 * Source: https://core.telegram.org/bots/api#forumtopicreopened
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ForumTopicReopened extends TelegramObject
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
