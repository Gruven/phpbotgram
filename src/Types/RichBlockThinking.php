<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A block with a 'Thinking…' placeholder, corresponding to the custom HTML tag <tg-thinking>. The block may be used only in sendRichMessageDraft, therefore it can't be received in messages. See https://t.me/addemoji/AIActions for examples of custom emoji, which are recommended for usage in the block.
 *
 * Source: https://core.telegram.org/bots/api#richblockthinking
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichBlockThinking extends RichBlock
{
  /**
   * @param list<array<array-key,mixed>|RichText|string>|RichText|string $text
   */
  public function __construct(
    public readonly array|RichText|string $text,
    public readonly string $type = 'thinking',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
