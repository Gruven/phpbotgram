<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a boost added to a chat or changed.
 *
 * Source: https://core.telegram.org/bots/api#chatboostupdated
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChatBoostUpdated extends TelegramObject
{
  public function __construct(
    public readonly Chat $chat,
    public readonly ChatBoost $boost,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
