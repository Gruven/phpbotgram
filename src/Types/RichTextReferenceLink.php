<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A link to a reference.
 *
 * Source: https://core.telegram.org/bots/api#richtextreferencelink
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichTextReferenceLink extends RichText
{
  /**
   * @param list<array<array-key,mixed>|RichText|string>|RichText|string $text
   */
  public function __construct(
    public readonly array|RichText|string $text,
    public readonly string $referenceName,
    public readonly string $type = 'reference_link',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
