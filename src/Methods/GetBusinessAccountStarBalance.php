<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Returns the amount of Telegram Stars owned by a managed business account. Requires the can_view_gifts_and_stars business bot right. Returns StarAmount on success.
 *
 * Source: https://core.telegram.org/bots/api#getbusinessaccountstarbalance
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class GetBusinessAccountStarBalance extends TelegramMethod
{
  public const string ApiMethod = 'getBusinessAccountStarBalance';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $businessConnectionId,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
