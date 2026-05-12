<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * This object represents an type of Menu button
 *
 * Source: https://core.telegram.org/bots/api#menubuttondefault
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum MenuButtonType: string
{
  case Default = 'default';
  case Commands = 'commands';
  case WebApp = 'web_app';
}
