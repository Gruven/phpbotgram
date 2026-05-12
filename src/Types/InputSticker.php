<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object describes a sticker to be added to a sticker set.
 *
 * Source: https://core.telegram.org/bots/api#inputsticker
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputSticker extends TelegramObject
{
  /**
   * @param list<string> $emojiList
   * @param list<string> $keywords
   */
  public function __construct(
    public readonly InputFile|string $sticker,
    public readonly string $format,
    public readonly array $emojiList,
    public readonly ?MaskPosition $maskPosition = null,
    public readonly ?array $keywords = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
