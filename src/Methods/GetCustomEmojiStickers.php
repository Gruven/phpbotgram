<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Sticker;

/**
 * Use this method to get information about custom emoji stickers by their identifiers. Returns an Array of Sticker objects.
 *
 * Source: https://core.telegram.org/bots/api#getcustomemojistickers
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<list<Sticker>>
 */
final class GetCustomEmojiStickers extends TelegramMethod
{
  public const string ApiMethod = 'getCustomEmojiStickers';
  public const string ReturnsType = 'list:Sticker';

  public function __construct(
    /** @var list<string> */
    public readonly array $customEmojiIds,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
