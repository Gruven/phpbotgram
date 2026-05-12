<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object contains information about the color scheme for a user's name, message replies and link previews based on a unique gift.
 *
 * Source: https://core.telegram.org/bots/api#uniquegiftcolors
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class UniqueGiftColors extends TelegramObject
{
  /**
   * @param list<int> $lightThemeOtherColors
   * @param list<int> $darkThemeOtherColors
   */
  public function __construct(
    public readonly string $modelCustomEmojiId,
    public readonly string $symbolCustomEmojiId,
    public readonly int $lightThemeMainColor,
    public readonly array $lightThemeOtherColors,
    public readonly int $darkThemeMainColor,
    public readonly array $darkThemeOtherColors,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
