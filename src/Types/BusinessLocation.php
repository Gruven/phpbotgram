<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Contains information about the location of a Telegram Business account.
 *
 * Source: https://core.telegram.org/bots/api#businesslocation
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BusinessLocation extends TelegramObject
{
  public function __construct(
    public readonly string $address,
    public readonly ?Location $location = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
