<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\BusinessConnection;

/**
 * Use this method to get information about the connection of the bot with a business account. Returns a BusinessConnection object on success.
 *
 * Source: https://core.telegram.org/bots/api#getbusinessconnection
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<BusinessConnection>
 */
final class GetBusinessConnection extends TelegramMethod
{
  public const string ApiMethod = 'getBusinessConnection';
  public const string ReturnsType = BusinessConnection::class;

  public function __construct(
    public readonly string $businessConnectionId,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
