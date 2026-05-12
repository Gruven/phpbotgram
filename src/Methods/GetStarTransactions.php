<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\StarTransactions;

/**
 * Returns the bot's Telegram Star transactions in chronological order. On success, returns a StarTransactions object.
 *
 * Source: https://core.telegram.org/bots/api#getstartransactions
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<StarTransactions>
 */
final class GetStarTransactions extends TelegramMethod
{
  public const string ApiMethod = 'getStarTransactions';
  public const string ReturnsType = StarTransactions::class;

  public function __construct(
    public readonly ?int $offset = null,
    public readonly ?int $limit = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
