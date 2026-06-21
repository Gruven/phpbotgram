<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A block with an animation, corresponding to the HTML tag <video>.
 *
 * Source: https://core.telegram.org/bots/api#richblockanimation
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichBlockAnimation extends RichBlock
{
  public function __construct(
    public readonly Animation $animation,
    public readonly string $type = 'animation',
    public readonly ?bool $hasSpoiler = null,
    public readonly ?RichBlockCaption $caption = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
