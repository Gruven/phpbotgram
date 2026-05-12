<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object describes the background of a gift.
 *
 * Source: https://core.telegram.org/bots/api#giftbackground
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class GiftBackground extends TelegramObject
{
  public function __construct(
    public readonly int $centerColor,
    public readonly int $edgeColor,
    public readonly int $textColor,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
