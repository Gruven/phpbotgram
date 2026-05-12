<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a portion of the price for goods or services.
 *
 * Source: https://core.telegram.org/bots/api#labeledprice
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class LabeledPrice extends MutableTelegramObject
{
  public function __construct(
    public readonly string $label,
    public readonly int $amount,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
