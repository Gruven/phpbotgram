<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * An expandable block for details disclosure, corresponding to the HTML tag <details>.
 *
 * Source: https://core.telegram.org/bots/api#richblockdetails
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichBlockDetails extends RichBlock
{
  /**
   * @param list<array<array-key,mixed>|RichText|string>|RichText|string $summary
   * @param list<RichBlock> $blocks
   */
  public function __construct(
    public readonly array|RichText|string $summary,
    public readonly array $blocks,
    public readonly string $type = 'details',
    public readonly ?bool $isOpen = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
