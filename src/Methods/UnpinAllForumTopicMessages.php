<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to clear the list of pinned messages in a forum topic in a forum supergroup chat or a private chat with a user. In the case of a supergroup chat the bot must be an administrator in the chat for this to work and must have the can_pin_messages administrator right in the supergroup. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#unpinallforumtopicmessages
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class UnpinAllForumTopicMessages extends TelegramMethod
{
  public const string ApiMethod = 'unpinAllForumTopicMessages';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int|string $chatId,
    public readonly int $messageThreadId,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
