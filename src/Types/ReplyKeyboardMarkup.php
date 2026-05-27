<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a custom keyboard with reply options (see Introduction to bots for details and examples). Not supported in channels and for messages sent on behalf of a business account.
 *
 * Source: https://core.telegram.org/bots/api#replykeyboardmarkup
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ReplyKeyboardMarkup extends MutableTelegramObject
{
  /**
   * @param list<list<KeyboardButton>> $keyboard
   */
  public function __construct(
    public readonly array $keyboard,
    public readonly ?bool $isPersistent = null,
    public readonly ?bool $resizeKeyboard = null,
    public readonly ?bool $oneTimeKeyboard = null,
    public readonly ?string $inputFieldPlaceholder = null,
    public readonly ?bool $selective = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
