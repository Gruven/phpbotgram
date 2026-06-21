<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A block with a map, corresponding to the custom HTML tag <tg-map>.
 *
 * Source: https://core.telegram.org/bots/api#richblockmap
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichBlockMap extends RichBlock
{
  public function __construct(
    public readonly Location $location,
    public readonly int $zoom,
    public readonly int $width,
    public readonly int $height,
    public readonly string $type = 'map',
    public readonly ?RichBlockCaption $caption = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
