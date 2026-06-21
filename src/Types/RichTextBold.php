<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A bold text.
 *
 * Source: https://core.telegram.org/bots/api#richtextbold
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichTextBold extends RichText
{
  /**
   * @param list<array<array-key,mixed>|RichText|string>|RichText|string $text
   */
  public function __construct(
    public readonly array|RichText|string $text,
    public readonly string $type = 'bold',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
