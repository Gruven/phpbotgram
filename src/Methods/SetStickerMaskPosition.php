<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\MaskPosition;

/**
 * Use this method to change the mask position of a mask sticker. The sticker must belong to a sticker set that was created by the bot. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#setstickermaskposition
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetStickerMaskPosition extends TelegramMethod
{
  public const string ApiMethod = 'setStickerMaskPosition';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $sticker,
    public readonly ?MaskPosition $maskPosition = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
