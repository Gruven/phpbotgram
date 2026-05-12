<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * This object represents the scope to which bot commands are applied.
 *
 * Source: https://core.telegram.org/bots/api#botcommandscope
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum BotCommandScopeType: string
{
  case Default = 'default';
  case AllPrivateChats = 'all_private_chats';
  case AllGroupChats = 'all_group_chats';
  case AllChatAdministrators = 'all_chat_administrators';
  case Chat = 'chat';
  case ChatAdministrators = 'chat_administrators';
  case ChatMember = 'chat_member';
}
