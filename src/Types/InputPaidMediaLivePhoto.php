<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * The paid media to send is a live photo.
 *
 * Source: https://core.telegram.org/bots/api#inputpaidmedialivephoto
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputPaidMediaLivePhoto extends InputPaidMedia
{
  public function __construct(
    public readonly string $media,
    public readonly string $photo,
    public readonly string $type = 'live_photo',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
