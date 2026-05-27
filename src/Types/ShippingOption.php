<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents one shipping option.
 *
 * Source: https://core.telegram.org/bots/api#shippingoption
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ShippingOption extends TelegramObject
{
  /**
   * @param list<LabeledPrice> $prices
   */
  public function __construct(
    public readonly string $id,
    public readonly string $title,
    public readonly array $prices,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
