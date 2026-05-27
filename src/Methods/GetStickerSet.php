<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\StickerSet;

/**
 * Use this method to get a sticker set. On success, a StickerSet object is returned.
 *
 * Source: https://core.telegram.org/bots/api#getstickerset
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<StickerSet>
 */
final class GetStickerSet extends TelegramMethod
{
  public const string ApiMethod = 'getStickerSet';
  public const string ReturnsType = StickerSet::class;

  public function __construct(
    public readonly string $name,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
