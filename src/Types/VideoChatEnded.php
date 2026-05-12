<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a service message about a video chat ended in the chat.
 *
 * Source: https://core.telegram.org/bots/api#videochatended
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class VideoChatEnded extends TelegramObject
{
  public function __construct(
    public readonly int $duration,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
