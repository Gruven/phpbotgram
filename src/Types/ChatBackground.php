<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a chat background.
 *
 * Source: https://core.telegram.org/bots/api#chatbackground
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChatBackground extends TelegramObject
{
  public function __construct(
    public readonly BackgroundType $type,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
