<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\ChatAdministratorRights;

/**
 * Use this method to change the default administrator rights requested by the bot when it's added as an administrator to groups or channels. These rights will be suggested to users, but they are free to modify the list before adding the bot. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#setmydefaultadministratorrights
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetMyDefaultAdministratorRights extends TelegramMethod
{
  public const string ApiMethod = 'setMyDefaultAdministratorRights';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly ?ChatAdministratorRights $rights = null,
    public readonly ?bool $forChannels = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
