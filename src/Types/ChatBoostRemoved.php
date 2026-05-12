<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * This object represents a boost removed from a chat.
 *
 * Source: https://core.telegram.org/bots/api#chatboostremoved
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChatBoostRemoved extends TelegramObject
{
  public function __construct(
    public readonly Chat $chat,
    public readonly string $boostId,
    public readonly DateTime $removeDate,
    public readonly ChatBoostSource $source,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
