<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A section heading, corresponding to the HTML tags <h1>, <h2>, <h3>, <h4>, <h5>, or <h6>.
 *
 * Source: https://core.telegram.org/bots/api#richblocksectionheading
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichBlockSectionHeading extends RichBlock
{
  /**
   * @param list<array<array-key,mixed>|RichText|string>|RichText|string $text
   */
  public function __construct(
    public readonly array|RichText|string $text,
    public readonly int $size,
    public readonly string $type = 'heading',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
