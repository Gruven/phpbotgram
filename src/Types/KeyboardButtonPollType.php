<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents type of a poll, which is allowed to be created and sent when the corresponding button is pressed.
 *
 * Source: https://core.telegram.org/bots/api#keyboardbuttonpolltype
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class KeyboardButtonPollType extends MutableTelegramObject
{
  public function __construct(
    public readonly ?string $type = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
