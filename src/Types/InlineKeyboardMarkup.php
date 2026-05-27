<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents an inline keyboard that appears right next to the message it belongs to.
 *
 * Source: https://core.telegram.org/bots/api#inlinekeyboardmarkup
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InlineKeyboardMarkup extends MutableTelegramObject
{
  /**
   * @param list<list<InlineKeyboardButton>> $inlineKeyboard
   */
  public function __construct(
    public readonly array $inlineKeyboard,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
