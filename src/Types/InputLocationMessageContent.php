<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents the content of a location message to be sent as the result of an inline query.
 *
 * Source: https://core.telegram.org/bots/api#inputlocationmessagecontent
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputLocationMessageContent extends InputMessageContent
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
