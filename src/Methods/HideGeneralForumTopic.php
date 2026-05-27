<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to hide the 'General' topic in a forum supergroup chat. The bot must be an administrator in the chat for this to work and must have the can_manage_topics administrator rights. The topic will be automatically closed if it was open. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#hidegeneralforumtopic
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class HideGeneralForumTopic extends TelegramMethod
{
  public const string ApiMethod = 'hideGeneralForumTopic';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int|string $chatId,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
