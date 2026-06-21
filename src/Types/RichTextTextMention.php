<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A mention of a Telegram user by their identifier.
 *
 * Source: https://core.telegram.org/bots/api#richtexttextmention
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichTextTextMention extends RichText
{
  /**
   * @param list<array<array-key,mixed>|RichText|string>|RichText|string $text
   */
  public function __construct(
    public readonly array|RichText|string $text,
    public readonly User $user,
    public readonly string $type = 'text_mention',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
