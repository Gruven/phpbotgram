<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object describes a gift received and owned by a user or a chat. Currently, it can be one of
 *  - OwnedGiftRegular
 *  - OwnedGiftUnique
 *
 * Source: https://core.telegram.org/bots/api#ownedgift
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
abstract class OwnedGift extends TelegramObject
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
