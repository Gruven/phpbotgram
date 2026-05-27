<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * This object represents a chat type
 *
 * Source: https://core.telegram.org/bots/api#chat
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum ChatType: string
{
  case Sender = 'sender';
  case Private = 'private';
  case Group = 'group';
  case Supergroup = 'supergroup';
  case Channel = 'channel';
}
