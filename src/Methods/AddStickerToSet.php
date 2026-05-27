<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\InputSticker;

/**
 * Use this method to add a new sticker to a set created by the bot. Emoji sticker sets can have up to 200 stickers. Other sticker sets can have up to 120 stickers. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#addstickertoset
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class AddStickerToSet extends TelegramMethod
{
  public const string ApiMethod = 'addStickerToSet';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int $userId,
    public readonly string $name,
    public readonly InputSticker $sticker,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
