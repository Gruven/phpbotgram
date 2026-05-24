<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\Keyboard;

use Gruven\PhpBotGram\Filters\CallbackData;
use Gruven\PhpBotGram\Types\CallbackGame;
use Gruven\PhpBotGram\Types\CopyTextButton;
use Gruven\PhpBotGram\Types\InlineKeyboardButton;
use Gruven\PhpBotGram\Types\InlineKeyboardMarkup;
use Gruven\PhpBotGram\Types\LoginUrl;
use Gruven\PhpBotGram\Types\SwitchInlineQueryChosenChat;
use Gruven\PhpBotGram\Types\WebAppInfo;

/**
 * Fluent builder for `InlineKeyboardMarkup`.
 *
 * Port of upstream `aiogram/utils/keyboard.py` — `InlineKeyboardBuilder` class.
 *
 * @extends KeyboardBuilder<InlineKeyboardButton>
 */
final class InlineKeyboardBuilder extends KeyboardBuilder
{
  /**
   * Maximum buttons per row for inline keyboards (Telegram limit: 8).
   */
  public const int MAX_WIDTH = 8;

  /**
   * Minimum row width.
   */
  public const int MIN_WIDTH = 1;

  /**
   * Maximum total buttons for inline keyboards (Telegram limit: 100).
   */
  public const int MAX_BUTTONS = 100;

  /**
   * @param null|list<list<InlineKeyboardButton>> $markup
   */
  public function __construct(?array $markup = null)
  {
    parent::__construct(InlineKeyboardButton::class, $markup);
  }

  /**
   * Create an `InlineKeyboardBuilder` from an existing `InlineKeyboardMarkup`.
   */
  public static function fromMarkup(InlineKeyboardMarkup $markup): self
  {
    return new self($markup->inlineKeyboard);
  }

  /**
   * Create a deep-copied clone of this builder.
   */
  public function copy(): self
  {
    return new self($this->export());
  }

  /**
   * Add a fully-specified `InlineKeyboardButton` to the markup by flowing
   * it through `add()`.
   *
   * Convenience factory that mirrors upstream's `InlineKeyboardBuilder.button()`.
   * When `$callbackData` is a `CallbackData` instance it is packed to its wire
   * string via `CallbackData::pack()`.
   *
   * @param null|CallbackData|string $callbackData
   *
   * @return static
   */
  public function button(
    string $text,
    ?string $url = null,
    CallbackData|string|null $callbackData = null,
    ?WebAppInfo $webApp = null,
    ?LoginUrl $loginUrl = null,
    ?string $switchInlineQuery = null,
    ?string $switchInlineQueryCurrentChat = null,
    ?SwitchInlineQueryChosenChat $switchInlineQueryChosenChat = null,
    ?CopyTextButton $copyText = null,
    ?CallbackGame $callbackGame = null,
    ?bool $pay = null,
    ?string $iconCustomEmojiId = null,
    ?string $style = null,
  ): static {
    $packed = $callbackData instanceof CallbackData ? $callbackData->pack() : $callbackData;

    $button = new InlineKeyboardButton(
      text: $text,
      iconCustomEmojiId: $iconCustomEmojiId,
      style: $style,
      url: $url,
      callbackData: $packed,
      webApp: $webApp,
      loginUrl: $loginUrl,
      switchInlineQuery: $switchInlineQuery,
      switchInlineQueryCurrentChat: $switchInlineQueryCurrentChat,
      switchInlineQueryChosenChat: $switchInlineQueryChosenChat,
      copyText: $copyText,
      callbackGame: $callbackGame,
      pay: $pay,
    );

    return $this->add($button);
  }

  /**
   * Wrap the current markup in an `InlineKeyboardMarkup` DTO.
   */
  public function asMarkup(): InlineKeyboardMarkup
  {
    return new InlineKeyboardMarkup(inlineKeyboard: $this->export());
  }
}
