<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A link to an anchor.
 *
 * Source: https://core.telegram.org/bots/api#richtextanchorlink
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichTextAnchorLink extends RichText
{
  /**
   * @param list<array<array-key,mixed>|RichText|string>|RichText|string $text
   */
  public function __construct(
    public readonly array|RichText|string $text,
    public readonly string $anchorName,
    public readonly string $type = 'anchor_link',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
