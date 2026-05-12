<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\ForumTopic;

/**
 * Use this method to create a topic in a forum supergroup chat or a private chat with a user. In the case of a supergroup chat the bot must be an administrator in the chat for this to work and must have the can_manage_topics administrator right. Returns information about the created topic as a ForumTopic object.
 *
 * Source: https://core.telegram.org/bots/api#createforumtopic
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<ForumTopic>
 */
final class CreateForumTopic extends TelegramMethod
{
  public const string ApiMethod = 'createForumTopic';
  public const string ReturnsType = ForumTopic::class;

  public function __construct(
    public readonly int|string $chatId,
    public readonly string $name,
    public readonly ?int $iconColor = null,
    public readonly ?string $iconCustomEmojiId = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
