<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to set the thumbnail of a custom emoji sticker set. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#setcustomemojistickersetthumbnail
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetCustomEmojiStickerSetThumbnail extends TelegramMethod
{
  public const string ApiMethod = 'setCustomEmojiStickerSetThumbnail';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $name,
    public readonly ?string $customEmojiId = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
