<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\UserChatBoosts;

/**
 * Use this method to get the list of boosts added to a chat by a user. Requires administrator rights in the chat. Returns a UserChatBoosts object.
 *
 * Source: https://core.telegram.org/bots/api#getuserchatboosts
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<UserChatBoosts>
 */
final class GetUserChatBoosts extends TelegramMethod
{
  public const string ApiMethod = 'getUserChatBoosts';
  public const string ReturnsType = UserChatBoosts::class;

  public function __construct(
    public readonly int|string $chatId,
    public readonly int $userId,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
