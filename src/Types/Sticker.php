<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Methods\DeleteStickerFromSet;
use Gruven\PhpBotGram\Methods\SetStickerPositionInSet;

/**
 * This object represents a sticker.
 *
 * Source: https://core.telegram.org/bots/api#sticker
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class Sticker extends TelegramObject
{
  public function __construct(
    public readonly string $fileId,
    public readonly string $fileUniqueId,
    public readonly string $type,
    public readonly int $width,
    public readonly int $height,
    public readonly bool $isAnimated,
    public readonly bool $isVideo,
    public readonly ?PhotoSize $thumbnail = null,
    public readonly ?string $emoji = null,
    public readonly ?string $setName = null,
    public readonly ?File $premiumAnimation = null,
    public readonly ?MaskPosition $maskPosition = null,
    public readonly ?string $customEmojiId = null,
    public readonly ?bool $needsRepainting = null,
    public readonly ?int $fileSize = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
  public function setPositionInSet(
    int $position,
  ): SetStickerPositionInSet {
    return new SetStickerPositionInSet(
      sticker: $this->fileId,
      position: $position,
      bot: $this->bot,
    );
  }
  public function deleteFromSet(
  ): DeleteStickerFromSet {
    return new DeleteStickerFromSet(
      sticker: $this->fileId,
      bot: $this->bot,
    );
  }
}
