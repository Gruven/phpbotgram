<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents the scope to which bot commands are applied. Currently, the following 7 scopes are supported:
 *  - BotCommandScopeDefault
 *  - BotCommandScopeAllPrivateChats
 *  - BotCommandScopeAllGroupChats
 *  - BotCommandScopeAllChatAdministrators
 *  - BotCommandScopeChat
 *  - BotCommandScopeChatAdministrators
 *  - BotCommandScopeChatMember
 *
 * Source: https://core.telegram.org/bots/api#botcommandscope
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
abstract class BotCommandScope extends TelegramObject
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
