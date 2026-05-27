<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a service message about a video chat started in the chat. Currently holds no information.
 *
 * Source: https://core.telegram.org/bots/api#videochatstarted
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class VideoChatStarted extends TelegramObject
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
