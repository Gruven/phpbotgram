<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * This object represents origin of a message.
 *
 * Source: https://core.telegram.org/bots/api#messageorigin
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum MessageOriginType: string
{
  case User = 'user';
  case HiddenUser = 'hidden_user';
  case Chat = 'chat';
  case Channel = 'channel';
}
