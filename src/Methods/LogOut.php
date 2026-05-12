<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to log out from the cloud Bot API server before launching the bot locally. You must log out the bot before running it locally, otherwise there is no guarantee that the bot will receive updates. After a successful call, you can immediately log in on a local server, but will not be able to log in back to the cloud Bot API server for 10 minutes. Returns True on success. Requires no parameters.
 *
 * Source: https://core.telegram.org/bots/api#logout
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class LogOut extends TelegramMethod
{
  public const string ApiMethod = 'logOut';
  public const string ReturnsType = 'bool';

  public function __construct(?Bot $bot = null)
  {
    parent::__construct($bot);
  }
}
