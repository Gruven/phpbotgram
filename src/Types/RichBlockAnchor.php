<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A block with an anchor, corresponding to the HTML tag <a> with the attribute name.
 *
 * Source: https://core.telegram.org/bots/api#richblockanchor
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichBlockAnchor extends RichBlock
{
  public function __construct(
    public readonly string $name,
    public readonly string $type = 'anchor',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
