<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A quotation with centered text, loosely corresponding to the HTML tag <aside>.
 *
 * Source: https://core.telegram.org/bots/api#richblockpullquotation
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichBlockPullQuotation extends RichBlock
{
  /**
   * @param list<array<array-key,mixed>|RichText|string>|RichText|string $text
   * @param null|list<array<array-key,mixed>|RichText|string>|RichText|string $credit
   */
  public function __construct(
    public readonly array|RichText|string $text,
    public readonly string $type = 'pullquote',
    public readonly array|RichText|string|null $credit = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
