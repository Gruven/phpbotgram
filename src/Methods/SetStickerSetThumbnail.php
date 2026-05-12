<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\InputFile;

/**
 * Use this method to set the thumbnail of a regular or mask sticker set. The format of the thumbnail file must match the format of the stickers in the set. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#setstickersetthumbnail
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetStickerSetThumbnail extends TelegramMethod
{
  public const string ApiMethod = 'setStickerSetThumbnail';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $name,
    public readonly int $userId,
    public readonly string $format,
    public readonly null|InputFile|string $thumbnail = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
