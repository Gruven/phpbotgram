<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents information about an order.
 *
 * Source: https://core.telegram.org/bots/api#orderinfo
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class OrderInfo extends TelegramObject
{
  public function __construct(
    public readonly ?string $name = null,
    public readonly ?string $phoneNumber = null,
    public readonly ?string $email = null,
    public readonly ?ShippingAddress $shippingAddress = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
