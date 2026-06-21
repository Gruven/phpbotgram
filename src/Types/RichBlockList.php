<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A list of blocks, corresponding to the HTML tag <ul> or <ol> with multiple nested tags <li>.
 *
 * Source: https://core.telegram.org/bots/api#richblocklist
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichBlockList extends RichBlock
{
  /**
   * @param list<RichBlockListItem> $items
   */
  public function __construct(
    public readonly array $items,
    public readonly string $type = 'list',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
