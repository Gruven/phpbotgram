<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a keyboard button to be used by a user of a Mini App.
 *
 * Source: https://core.telegram.org/bots/api#preparedkeyboardbutton
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PreparedKeyboardButton extends TelegramObject
{
  public function __construct(
    public readonly string $id,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
