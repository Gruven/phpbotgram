<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents one button of an inline keyboard. Exactly one of the fields other than text, icon_custom_emoji_id, and style must be used to specify the type of the button.
 *
 * Source: https://core.telegram.org/bots/api#inlinekeyboardbutton
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InlineKeyboardButton extends MutableTelegramObject
{
  public function __construct(
    public readonly string $text,
    public readonly ?string $iconCustomEmojiId = null,
    public readonly ?string $style = null,
    public readonly ?string $url = null,
    public readonly ?string $callbackData = null,
    public readonly ?WebAppInfo $webApp = null,
    public readonly ?LoginUrl $loginUrl = null,
    public readonly ?string $switchInlineQuery = null,
    public readonly ?string $switchInlineQueryCurrentChat = null,
    public readonly ?SwitchInlineQueryChosenChat $switchInlineQueryChosenChat = null,
    public readonly ?CopyTextButton $copyText = null,
    public readonly ?CallbackGame $callbackGame = null,
    public readonly ?bool $pay = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
