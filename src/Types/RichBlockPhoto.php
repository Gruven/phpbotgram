<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A block with a photo, corresponding to the HTML tag <photo>.
 *
 * Source: https://core.telegram.org/bots/api#richblockphoto
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichBlockPhoto extends RichBlock
{
  /**
   * @param list<PhotoSize> $photo
   */
  public function __construct(
    public readonly array $photo,
    public readonly string $type = 'photo',
    public readonly ?bool $hasSpoiler = null,
    public readonly ?RichBlockCaption $caption = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
