<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * This object represents chat member status.
 *
 * Source: https://core.telegram.org/bots/api#chatmember
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum ChatMemberStatus: string
{
  case Creator = 'creator';
  case Administrator = 'administrator';
  case Member = 'member';
  case Restricted = 'restricted';
  case Left = 'left';
  case Kicked = 'kicked';
}
