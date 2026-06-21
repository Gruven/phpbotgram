<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A divider, corresponding to the HTML tag <hr/>.
 *
 * Source: https://core.telegram.org/bots/api#richblockdivider
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichBlockDivider extends RichBlock
{
  public function __construct(
    public readonly string $type = 'divider',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
