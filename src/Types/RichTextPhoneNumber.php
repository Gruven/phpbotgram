<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A text with a phone number.
 *
 * Source: https://core.telegram.org/bots/api#richtextphonenumber
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichTextPhoneNumber extends RichText
{
  /**
   * @param list<array<array-key,mixed>|RichText|string>|RichText|string $text
   */
  public function __construct(
    public readonly array|RichText|string $text,
    public readonly string $phoneNumber,
    public readonly string $type = 'phone_number',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
