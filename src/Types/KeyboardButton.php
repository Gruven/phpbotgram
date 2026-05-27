<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents one button of the reply keyboard. At most one of the fields other than text, icon_custom_emoji_id, and style must be used to specify the type of the button. For simple text buttons, String can be used instead of this object to specify the button text.
 *
 * Source: https://core.telegram.org/bots/api#keyboardbutton
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class KeyboardButton extends MutableTelegramObject
{
  public function __construct(
    public readonly string $text,
    public readonly ?string $iconCustomEmojiId = null,
    public readonly ?string $style = null,
    public readonly ?KeyboardButtonRequestUsers $requestUsers = null,
    public readonly ?KeyboardButtonRequestChat $requestChat = null,
    public readonly ?KeyboardButtonRequestManagedBot $requestManagedBot = null,
    public readonly ?bool $requestContact = null,
    public readonly ?bool $requestLocation = null,
    public readonly ?KeyboardButtonPollType $requestPoll = null,
    public readonly ?WebAppInfo $webApp = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
