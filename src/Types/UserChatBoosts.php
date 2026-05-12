<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a list of boosts added to a chat by a user.
 *
 * Source: https://core.telegram.org/bots/api#userchatboosts
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class UserChatBoosts extends TelegramObject
{
  /**
   * @param list<ChatBoost> $boosts
   */
  public function __construct(
    public readonly array $boosts,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
