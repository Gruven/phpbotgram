<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A slideshow, corresponding to the custom HTML tag <tg-slideshow>.
 *
 * Source: https://core.telegram.org/bots/api#richblockslideshow
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichBlockSlideshow extends RichBlock
{
  /**
   * @param list<RichBlock> $blocks
   */
  public function __construct(
    public readonly array $blocks,
    public readonly string $type = 'slideshow',
    public readonly ?RichBlockCaption $caption = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
