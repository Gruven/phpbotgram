<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A text paragraph, corresponding to the HTML tag <p>.
 *
 * Source: https://core.telegram.org/bots/api#richblockparagraph
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichBlockParagraph extends RichBlock
{
  /**
   * @param list<array<array-key,mixed>|RichText|string>|RichText|string $text
   */
  public function __construct(
    public readonly array|RichText|string $text,
    public readonly string $type = 'paragraph',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
