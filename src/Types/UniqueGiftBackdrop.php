<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object describes the backdrop of a unique gift.
 *
 * Source: https://core.telegram.org/bots/api#uniquegiftbackdrop
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class UniqueGiftBackdrop extends TelegramObject
{
  public function __construct(
    public readonly string $name,
    public readonly UniqueGiftBackdropColors $colors,
    public readonly int $rarityPerMille,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
