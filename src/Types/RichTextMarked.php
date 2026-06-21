<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A marked text.
 *
 * Source: https://core.telegram.org/bots/api#richtextmarked
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichTextMarked extends RichText
{
  /**
   * @param list<array<array-key,mixed>|RichText|string>|RichText|string $text
   */
  public function __construct(
    public readonly array|RichText|string $text,
    public readonly string $type = 'marked',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
