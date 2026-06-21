<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A table, corresponding to the HTML tag <table>.
 *
 * Source: https://core.telegram.org/bots/api#richblocktable
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichBlockTable extends RichBlock
{
  /**
   * @param list<list<RichBlockTableCell>> $cells
   * @param null|list<array<array-key,mixed>|RichText|string>|RichText|string $caption
   */
  public function __construct(
    public readonly array $cells,
    public readonly string $type = 'table',
    public readonly ?bool $isBordered = null,
    public readonly ?bool $isStriped = null,
    public readonly array|RichText|string|null $caption = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
