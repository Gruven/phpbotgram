<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\Keyboard;

use Gruven\PhpBotGram\Types\KeyboardButton;
use Gruven\PhpBotGram\Types\KeyboardButtonPollType;
use Gruven\PhpBotGram\Types\KeyboardButtonRequestChat;
use Gruven\PhpBotGram\Types\KeyboardButtonRequestManagedBot;
use Gruven\PhpBotGram\Types\KeyboardButtonRequestUsers;
use Gruven\PhpBotGram\Types\ReplyKeyboardMarkup;
use Gruven\PhpBotGram\Types\WebAppInfo;

/**
 * Fluent builder for `ReplyKeyboardMarkup`.
 *
 * Port of upstream `aiogram/utils/keyboard.py` — `ReplyKeyboardBuilder` class.
 *
 * @extends KeyboardBuilder<KeyboardButton>
 */
final class ReplyKeyboardBuilder extends KeyboardBuilder
{
  /**
   * Maximum buttons per row for reply keyboards.
   *
   * Matches upstream `aiogram/utils/keyboard.py:374` (`max_width: int = 10`).
   */
  public const int MAX_WIDTH = 10;

  /**
   * Minimum row width.
   */
  public const int MIN_WIDTH = 1;

  /**
   * Maximum total buttons for reply keyboards (Telegram limit: 300).
   */
  public const int MAX_BUTTONS = 300;

  /**
   * @param null|list<list<KeyboardButton>> $markup
   */
  public function __construct(?array $markup = null)
  {
    parent::__construct(KeyboardButton::class, $markup);
  }

  /**
   * Create a `ReplyKeyboardBuilder` from an existing `ReplyKeyboardMarkup`.
   */
  public static function fromMarkup(ReplyKeyboardMarkup $markup): self
  {
    return new self($markup->keyboard);
  }

  /**
   * Create a deep-copied clone of this builder.
   */
  public function copy(): self
  {
    return new self($this->export());
  }

  /**
   * Add a `KeyboardButton` by specifying its fields directly.
   *
   * Mirrors upstream's `ReplyKeyboardBuilder.button()`.
   *
   * @return static
   */
  public function button(
    string $text,
    ?KeyboardButtonRequestUsers $requestUsers = null,
    ?KeyboardButtonRequestChat $requestChat = null,
    ?KeyboardButtonRequestManagedBot $requestManagedBot = null,
    ?bool $requestContact = null,
    ?bool $requestLocation = null,
    ?KeyboardButtonPollType $requestPoll = null,
    ?WebAppInfo $webApp = null,
    ?string $iconCustomEmojiId = null,
    ?string $style = null,
  ): static {
    $button = new KeyboardButton(
      text: $text,
      iconCustomEmojiId: $iconCustomEmojiId,
      style: $style,
      requestUsers: $requestUsers,
      requestChat: $requestChat,
      requestManagedBot: $requestManagedBot,
      requestContact: $requestContact,
      requestLocation: $requestLocation,
      requestPoll: $requestPoll,
      webApp: $webApp,
    );

    return $this->add($button);
  }

  /**
   * Wrap the current markup in a `ReplyKeyboardMarkup` DTO.
   *
   * @param null|bool $isPersistent Show the keyboard persistently.
   * @param null|bool $resizeKeyboard Request clients to resize the keyboard.
   * @param null|bool $oneTimeKeyboard Hide the keyboard after a button is pressed.
   * @param null|string $inputFieldPlaceholder Placeholder text for the input field.
   * @param null|bool $selective Show keyboard to specific users only.
   */
  public function asMarkup(
    ?bool $isPersistent = null,
    ?bool $resizeKeyboard = null,
    ?bool $oneTimeKeyboard = null,
    ?string $inputFieldPlaceholder = null,
    ?bool $selective = null,
  ): ReplyKeyboardMarkup {
    return new ReplyKeyboardMarkup(
      keyboard: $this->export(),
      isPersistent: $isPersistent,
      resizeKeyboard: $resizeKeyboard,
      oneTimeKeyboard: $oneTimeKeyboard,
      inputFieldPlaceholder: $inputFieldPlaceholder,
      selective: $selective,
    );
  }
}
