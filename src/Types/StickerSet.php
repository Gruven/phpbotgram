<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a sticker set.
 *
 * Source: https://core.telegram.org/bots/api#stickerset
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class StickerSet extends TelegramObject
{
  /**
   * @param list<Sticker> $stickers
   */
  public function __construct(
    public readonly string $name,
    public readonly string $title,
    public readonly string $stickerType,
    public readonly array $stickers,
    public readonly ?PhotoSize $thumbnail = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
