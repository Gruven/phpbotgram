<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object contains information about one member of a chat. Currently, the following 6 types of chat members are supported:
 *  - ChatMemberOwner
 *  - ChatMemberAdministrator
 *  - ChatMemberMember
 *  - ChatMemberRestricted
 *  - ChatMemberLeft
 *  - ChatMemberBanned
 *
 * Source: https://core.telegram.org/bots/api#chatmember
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
abstract class ChatMember extends TelegramObject
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
