<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a shipping address.
 *
 * Source: https://core.telegram.org/bots/api#shippingaddress
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ShippingAddress extends TelegramObject
{
  public function __construct(
    public readonly string $countryCode,
    public readonly string $state,
    public readonly string $city,
    public readonly string $streetLine1,
    public readonly string $streetLine2,
    public readonly string $postCode,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
