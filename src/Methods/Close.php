<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to close the bot instance before moving it from one local server to another. You need to delete the webhook before calling this method to ensure that the bot isn't launched again after server restart. The method will return error 429 in the first 10 minutes after the bot is launched. Returns True on success. Requires no parameters.
 *
 * Source: https://core.telegram.org/bots/api#close
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class Close extends TelegramMethod
{
  public const string ApiMethod = 'close';
  public const string ReturnsType = 'bool';

  public function __construct(?Bot $bot = null)
  {
    parent::__construct($bot);
  }
}
