<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A cashtag.
 *
 * Source: https://core.telegram.org/bots/api#richtextcashtag
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichTextCashtag extends RichText
{
  /**
   * @param list<array<array-key,mixed>|RichText|string>|RichText|string $text
   */
  public function __construct(
    public readonly array|RichText|string $text,
    public readonly string $cashtag,
    public readonly string $type = 'cashtag',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
