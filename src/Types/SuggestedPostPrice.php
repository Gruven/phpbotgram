<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes the price of a suggested post.
 *
 * Source: https://core.telegram.org/bots/api#suggestedpostprice
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class SuggestedPostPrice extends TelegramObject
{
  public function __construct(
    public readonly string $currency,
    public readonly int $amount,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
