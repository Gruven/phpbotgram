<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object contains basic information about an invoice.
 *
 * Source: https://core.telegram.org/bots/api#invoice
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class Invoice extends TelegramObject
{
  public function __construct(
    public readonly string $title,
    public readonly string $description,
    public readonly string $startParameter,
    public readonly string $currency,
    public readonly int $totalAmount,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
