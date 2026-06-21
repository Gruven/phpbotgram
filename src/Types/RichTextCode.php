<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A monowidth text.
 *
 * Source: https://core.telegram.org/bots/api#richtextcode
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichTextCode extends RichText
{
  /**
   * @param list<array<array-key,mixed>|RichText|string>|RichText|string $text
   */
  public function __construct(
    public readonly array|RichText|string $text,
    public readonly string $type = 'code',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
