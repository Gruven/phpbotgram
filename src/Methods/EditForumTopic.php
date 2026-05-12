<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to edit name and icon of a topic in a forum supergroup chat or a private chat with a user. In the case of a supergroup chat the bot must be an administrator in the chat for this to work and must have the can_manage_topics administrator rights, unless it is the creator of the topic. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#editforumtopic
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class EditForumTopic extends TelegramMethod
{
  public const string ApiMethod = 'editForumTopic';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int|string $chatId,
    public readonly int $messageThreadId,
    public readonly ?string $name = null,
    public readonly ?string $iconCustomEmojiId = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
