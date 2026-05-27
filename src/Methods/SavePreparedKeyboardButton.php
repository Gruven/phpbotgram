<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\KeyboardButton;
use Gruven\PhpBotGram\Types\PreparedKeyboardButton;

/**
 * Stores a keyboard button that can be used by a user within a Mini App. Returns a PreparedKeyboardButton object.
 *
 * Source: https://core.telegram.org/bots/api#savepreparedkeyboardbutton
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<PreparedKeyboardButton>
 */
final class SavePreparedKeyboardButton extends TelegramMethod
{
  public const string ApiMethod = 'savePreparedKeyboardButton';
  public const string ReturnsType = PreparedKeyboardButton::class;

  public function __construct(
    public readonly int $userId,
    public readonly KeyboardButton $button,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
