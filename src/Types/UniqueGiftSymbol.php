<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object describes the symbol shown on the pattern of a unique gift.
 *
 * Source: https://core.telegram.org/bots/api#uniquegiftsymbol
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class UniqueGiftSymbol extends TelegramObject
{
  public function __construct(
    public readonly string $name,
    public readonly Sticker $sticker,
    public readonly int $rarityPerMille,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
