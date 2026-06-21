<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A block quotation, corresponding to the HTML tag <blockquote>.
 *
 * Source: https://core.telegram.org/bots/api#richblockblockquotation
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichBlockBlockQuotation extends RichBlock
{
  /**
   * @param list<RichBlock> $blocks
   * @param null|list<array<array-key,mixed>|RichText|string>|RichText|string $credit
   */
  public function __construct(
    public readonly array $blocks,
    public readonly string $type = 'blockquote',
    public readonly array|RichText|string|null $credit = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
