<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes the physical address of a location.
 *
 * Source: https://core.telegram.org/bots/api#locationaddress
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class LocationAddress extends TelegramObject
{
  public function __construct(
    public readonly string $countryCode,
    public readonly ?string $state = null,
    public readonly ?string $city = null,
    public readonly ?string $street = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
