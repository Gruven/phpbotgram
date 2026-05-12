<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to delete a sticker from a set created by the bot. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#deletestickerfromset
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class DeleteStickerFromSet extends TelegramMethod
{
  public const string ApiMethod = 'deleteStickerFromSet';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $sticker,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
