<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A text with a bank card number.
 *
 * Source: https://core.telegram.org/bots/api#richtextbankcardnumber
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichTextBankCardNumber extends RichText
{
  /**
   * @param list<array<array-key,mixed>|RichText|string>|RichText|string $text
   */
  public function __construct(
    public readonly array|RichText|string $text,
    public readonly string $bankCardNumber,
    public readonly string $type = 'bank_card_number',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
