<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to set the title of a created sticker set. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#setstickersettitle
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetStickerSetTitle extends TelegramMethod
{
  public const string ApiMethod = 'setStickerSetTitle';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $name,
    public readonly string $title,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
