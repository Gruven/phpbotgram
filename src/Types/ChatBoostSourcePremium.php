<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * The boost was obtained by subscribing to Telegram Premium or by gifting a Telegram Premium subscription to another user.
 *
 * Source: https://core.telegram.org/bots/api#chatboostsourcepremium
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChatBoostSourcePremium extends ChatBoostSource
{
  public function __construct(
    public readonly User $user,
    public readonly string $source = 'premium',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
