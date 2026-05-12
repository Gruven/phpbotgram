<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\InputSticker;

/**
 * Use this method to replace an existing sticker in a sticker set with a new one. The method is equivalent to calling deleteStickerFromSet, then addStickerToSet, then setStickerPositionInSet. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#replacestickerinset
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class ReplaceStickerInSet extends TelegramMethod
{
  public const string ApiMethod = 'replaceStickerInSet';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int $userId,
    public readonly string $name,
    public readonly string $oldSticker,
    public readonly InputSticker $sticker,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
