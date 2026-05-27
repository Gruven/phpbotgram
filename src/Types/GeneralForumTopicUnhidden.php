<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a service message about General forum topic unhidden in the chat. Currently holds no information.
 *
 * Source: https://core.telegram.org/bots/api#generalforumtopicunhidden
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class GeneralForumTopicUnhidden extends TelegramObject
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
