<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object describes the types of gifts that can be gifted to a user or a chat.
 *
 * Source: https://core.telegram.org/bots/api#acceptedgifttypes
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class AcceptedGiftTypes extends TelegramObject
{
  public function __construct(
    public readonly bool $unlimitedGifts,
    public readonly bool $limitedGifts,
    public readonly bool $uniqueGifts,
    public readonly bool $premiumSubscription,
    public readonly bool $giftsFromChannels,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
