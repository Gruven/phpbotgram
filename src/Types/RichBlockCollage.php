<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A collage, corresponding to the custom HTML tag <tg-collage>.
 *
 * Source: https://core.telegram.org/bots/api#richblockcollage
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichBlockCollage extends RichBlock
{
  /**
   * @param list<RichBlock> $blocks
   */
  public function __construct(
    public readonly array $blocks,
    public readonly string $type = 'collage',
    public readonly ?RichBlockCaption $caption = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
