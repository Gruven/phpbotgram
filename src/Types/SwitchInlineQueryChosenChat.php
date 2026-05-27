<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents an inline button that switches the current user to inline mode in a chosen chat, with an optional default inline query.
 *
 * Source: https://core.telegram.org/bots/api#switchinlinequerychosenchat
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class SwitchInlineQueryChosenChat extends TelegramObject
{
  public function __construct(
    public readonly ?string $query = null,
    public readonly ?bool $allowUserChats = null,
    public readonly ?bool $allowBotChats = null,
    public readonly ?bool $allowGroupChats = null,
    public readonly ?bool $allowChannelChats = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
