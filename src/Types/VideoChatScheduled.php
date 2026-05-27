<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * This object represents a service message about a video chat scheduled in the chat.
 *
 * Source: https://core.telegram.org/bots/api#videochatscheduled
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class VideoChatScheduled extends TelegramObject
{
  public function __construct(
    public readonly DateTime $startDate,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
