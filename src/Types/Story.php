<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a story.
 *
 * Source: https://core.telegram.org/bots/api#story
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class Story extends TelegramObject
{
  public function __construct(
    public readonly Chat $chat,
    public readonly int $id,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
