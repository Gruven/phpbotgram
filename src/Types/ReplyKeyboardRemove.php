<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Upon receiving a message with this object, Telegram clients will remove the current custom keyboard and display the default letter-keyboard. By default, custom keyboards are displayed until a new keyboard is sent by a bot. An exception is made for one-time keyboards that are hidden immediately after the user presses a button (see ReplyKeyboardMarkup). Not supported in channels and for messages sent on behalf of a business account.
 *
 * Source: https://core.telegram.org/bots/api#replykeyboardremove
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ReplyKeyboardRemove extends MutableTelegramObject
{
  public function __construct(
    public readonly bool $removeKeyboard = true,
    public readonly ?bool $selective = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
