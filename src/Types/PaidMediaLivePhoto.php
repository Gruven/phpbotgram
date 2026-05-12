<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * The paid media is a live photo.
 *
 * Source: https://core.telegram.org/bots/api#paidmedialivephoto
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PaidMediaLivePhoto extends PaidMedia
{
  public function __construct(
    public readonly LivePhoto $livePhoto,
    public readonly string $type = 'live_photo',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
