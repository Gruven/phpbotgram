<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\StarAmount;

/**
 * A method to get the current Telegram Stars balance of the bot. Requires no parameters. On success, returns a StarAmount object.
 *
 * Source: https://core.telegram.org/bots/api#getmystarbalance
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<StarAmount>
 */
final class GetMyStarBalance extends TelegramMethod
{
  public const string ApiMethod = 'getMyStarBalance';
  public const string ReturnsType = StarAmount::class;

  public function __construct(?Bot $bot = null)
  {
    parent::__construct($bot);
  }
}
