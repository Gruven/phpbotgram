<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a service message about a change in the price of direct messages sent to a channel chat.
 *
 * Source: https://core.telegram.org/bots/api#directmessagepricechanged
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class DirectMessagePriceChanged extends TelegramObject
{
  public function __construct(
    public readonly bool $areDirectMessagesEnabled,
    public readonly ?int $directMessageStarCount = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
