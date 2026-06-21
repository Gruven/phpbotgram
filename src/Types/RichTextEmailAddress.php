<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A text with an email address.
 *
 * Source: https://core.telegram.org/bots/api#richtextemailaddress
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichTextEmailAddress extends RichText
{
  /**
   * @param list<array<array-key,mixed>|RichText|string>|RichText|string $text
   */
  public function __construct(
    public readonly array|RichText|string $text,
    public readonly string $emailAddress,
    public readonly string $type = 'email_address',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
