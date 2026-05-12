<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to get the last messages from the personal chat (i.e., the chat currently added to their profile) of a given user. On success, an array of Message objects is returned.
 *
 * Source: https://core.telegram.org/bots/api#getuserpersonalchatmessages
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class GetUserPersonalChatMessages extends TelegramMethod
{
  public const string ApiMethod = 'getUserPersonalChatMessages';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int $userId,
    public readonly int $limit,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
