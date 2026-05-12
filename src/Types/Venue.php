<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a venue.
 *
 * Source: https://core.telegram.org/bots/api#venue
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class Venue extends TelegramObject
{
  public function __construct(
    public readonly Location $location,
    public readonly string $title,
    public readonly string $address,
    public readonly ?string $foursquareId = null,
    public readonly ?string $foursquareType = null,
    public readonly ?string $googlePlaceId = null,
    public readonly ?string $googlePlaceType = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
