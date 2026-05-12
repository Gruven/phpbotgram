<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to change search keywords assigned to a regular or custom emoji sticker. The sticker must belong to a sticker set created by the bot. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#setstickerkeywords
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetStickerKeywords extends TelegramMethod
{
  public const string ApiMethod = 'setStickerKeywords';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $sticker,
    /** @var list<string> */
    public readonly ?array $keywords = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
