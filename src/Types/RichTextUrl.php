<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A text with a link.
 *
 * Source: https://core.telegram.org/bots/api#richtexturl
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichTextUrl extends RichText
{
  /**
   * @param list<array<array-key,mixed>|RichText|string>|RichText|string $text
   */
  public function __construct(
    public readonly array|RichText|string $text,
    public readonly string $url,
    public readonly string $type = 'url',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
