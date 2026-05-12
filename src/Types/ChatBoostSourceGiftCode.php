<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * The boost was obtained by the creation of Telegram Premium gift codes to boost a chat. Each such code boosts the chat 4 times for the duration of the corresponding Telegram Premium subscription.
 *
 * Source: https://core.telegram.org/bots/api#chatboostsourcegiftcode
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChatBoostSourceGiftCode extends ChatBoostSource
{
  public function __construct(
    public readonly string $source,
    public readonly User $user,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
