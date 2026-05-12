<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * This object represents a button style (inline- or reply-keyboard).
 *
 * Sources:
 *   * https://core.telegram.org/bots/api#inlinekeyboardbutton
 *   * https://core.telegram.org/bots/api#keyboardbutton
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum ButtonStyle: string
{
  case Danger = 'danger';
  case Success = 'success';
  case Primary = 'primary';
}
