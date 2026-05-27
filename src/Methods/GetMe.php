<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\User;

/**
 * A simple method for testing your bot's authentication token. Requires no parameters. Returns basic information about the bot in form of a User object.
 *
 * Source: https://core.telegram.org/bots/api#getme
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<User>
 */
final class GetMe extends TelegramMethod
{
  public const string ApiMethod = 'getMe';
  public const string ReturnsType = User::class;

  public function __construct(?Bot $bot = null)
  {
    parent::__construct($bot);
  }
}
