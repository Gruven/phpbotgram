<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to change the list of emoji assigned to a regular or custom emoji sticker. The sticker must belong to a sticker set created by the bot. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#setstickeremojilist
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetStickerEmojiList extends TelegramMethod
{
  public const string ApiMethod = 'setStickerEmojiList';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $sticker,
    /** @var list<string> */
    public readonly array $emojiList,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
