<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object describes the model of a unique gift.
 *
 * Source: https://core.telegram.org/bots/api#uniquegiftmodel
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class UniqueGiftModel extends TelegramObject
{
  public function __construct(
    public readonly string $name,
    public readonly Sticker $sticker,
    public readonly int $rarityPerMille,
    public readonly ?string $rarity = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
