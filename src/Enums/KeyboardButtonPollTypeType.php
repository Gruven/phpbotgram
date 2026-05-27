<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * This object represents type of a poll, which is allowed to be created and sent when the corresponding button is pressed.
 *
 * Source: https://core.telegram.org/bots/api#keyboardbuttonpolltype
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum KeyboardButtonPollTypeType: string
{
  case Quiz = 'quiz';
  case Regular = 'regular';
}
