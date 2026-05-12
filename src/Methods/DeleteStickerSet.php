<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to delete a sticker set that was created by the bot. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#deletestickerset
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class DeleteStickerSet extends TelegramMethod
{
  public const string ApiMethod = 'deleteStickerSet';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $name,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
