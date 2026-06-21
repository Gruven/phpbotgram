<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Cell in a table.
 *
 * Source: https://core.telegram.org/bots/api#richblocktablecell
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichBlockTableCell extends TelegramObject
{
  /**
   * @param null|list<array<array-key,mixed>|RichText|string>|RichText|string $text
   */
  public function __construct(
    public readonly string $align,
    public readonly string $valign,
    public readonly array|RichText|string|null $text = null,
    public readonly ?bool $isHeader = null,
    public readonly ?int $colspan = null,
    public readonly ?int $rowspan = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
