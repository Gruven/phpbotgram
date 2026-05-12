<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents the content of a venue message to be sent as the result of an inline query.
 *
 * Source: https://core.telegram.org/bots/api#inputvenuemessagecontent
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputVenueMessageContent extends InputMessageContent
{
  public function __construct(
    public readonly float $latitude,
    public readonly float $longitude,
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
