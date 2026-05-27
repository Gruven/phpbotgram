<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a service message about a change in the price of paid messages within a chat.
 *
 * Source: https://core.telegram.org/bots/api#paidmessagepricechanged
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PaidMessagePriceChanged extends TelegramObject
{
  public function __construct(
    public readonly int $paidMessageStarCount,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
