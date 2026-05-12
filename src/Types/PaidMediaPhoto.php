<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * The paid media is a photo.
 *
 * Source: https://core.telegram.org/bots/api#paidmediaphoto
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PaidMediaPhoto extends PaidMedia
{
  /**
   * @param list<PhotoSize> $photo
   */
  public function __construct(
    public readonly string $type,
    public readonly array $photo,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
