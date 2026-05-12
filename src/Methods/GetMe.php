<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\User;

/**
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
