<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents a location to be sent.
 *
 * Source: https://core.telegram.org/bots/api#inputmedialocation
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputMediaLocation extends InputPollMedia implements InputPollOptionMediaInterface
{
  public function __construct(
    public readonly float $latitude,
    public readonly float $longitude,
    public readonly string $type = 'location',
    public readonly ?float $horizontalAccuracy = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
