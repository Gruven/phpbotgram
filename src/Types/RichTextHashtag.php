<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A hashtag.
 *
 * Source: https://core.telegram.org/bots/api#richtexthashtag
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichTextHashtag extends RichText
{
  /**
   * @param list<array<array-key,mixed>|RichText|string>|RichText|string $text
   */
  public function __construct(
    public readonly array|RichText|string $text,
    public readonly string $hashtag,
    public readonly string $type = 'hashtag',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
