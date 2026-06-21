<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * At most one of the optional fields can be present in any given object.
 *
 * Source: https://core.telegram.org/bots/api#pollmedia
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PollMedia extends TelegramObject
{
  /**
   * @param null|list<PhotoSize> $photo
   */
  public function __construct(
    public readonly ?Animation $animation = null,
    public readonly ?Audio $audio = null,
    public readonly ?Document $document = null,
    public readonly ?Link $link = null,
    public readonly ?LivePhoto $livePhoto = null,
    public readonly ?Location $location = null,
    public readonly ?array $photo = null,
    public readonly ?Sticker $sticker = null,
    public readonly ?Venue $venue = null,
    public readonly ?Video $video = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
