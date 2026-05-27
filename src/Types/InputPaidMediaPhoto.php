<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * The paid media to send is a photo.
 *
 * Source: https://core.telegram.org/bots/api#inputpaidmediaphoto
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputPaidMediaPhoto extends InputPaidMedia
{
  public function __construct(
    public readonly InputFile|string $media,
    public readonly string $type = 'photo',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
