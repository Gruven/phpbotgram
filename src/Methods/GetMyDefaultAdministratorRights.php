<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\ChatAdministratorRights;

/**
 * Use this method to get the current default administrator rights of the bot. Returns ChatAdministratorRights on success.
 *
 * Source: https://core.telegram.org/bots/api#getmydefaultadministratorrights
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<ChatAdministratorRights>
 */
final class GetMyDefaultAdministratorRights extends TelegramMethod
{
  public const string ApiMethod = 'getMyDefaultAdministratorRights';
  public const string ReturnsType = ChatAdministratorRights::class;

  public function __construct(
    public readonly ?bool $forChannels = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
