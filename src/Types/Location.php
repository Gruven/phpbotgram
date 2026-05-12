<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a point on the map.
 *
 * Source: https://core.telegram.org/bots/api#location
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class Location extends TelegramObject
{
  public function __construct(
    public readonly float $latitude,
    public readonly float $longitude,
    public readonly ?float $horizontalAccuracy = null,
    public readonly ?int $livePeriod = null,
    public readonly ?int $heading = null,
    public readonly ?int $proximityAlertRadius = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
