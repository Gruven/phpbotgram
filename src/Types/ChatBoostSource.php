<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object describes the source of a chat boost. It can be one of
 *  - ChatBoostSourcePremium
 *  - ChatBoostSourceGiftCode
 *  - ChatBoostSourceGiveaway
 *
 * Source: https://core.telegram.org/bots/api#chatboostsource
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
abstract class ChatBoostSource extends TelegramObject
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
