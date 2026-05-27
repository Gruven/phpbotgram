<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents the scope of bot commands, covering a specific member of a group or supergroup chat.
 *
 * Source: https://core.telegram.org/bots/api#botcommandscopechatmember
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BotCommandScopeChatMember extends BotCommandScope
{
  public function __construct(
    public readonly int|string $chatId,
    public readonly int $userId,
    public readonly string $type = 'chat_member',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
