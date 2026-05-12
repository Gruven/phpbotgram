<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Removes the profile photo of the bot. Requires no parameters. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#removemyprofilephoto
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class RemoveMyProfilePhoto extends TelegramMethod
{
  public const string ApiMethod = 'removeMyProfilePhoto';
  public const string ReturnsType = 'bool';

  public function __construct(?Bot $bot = null)
  {
    parent::__construct($bot);
  }
}
