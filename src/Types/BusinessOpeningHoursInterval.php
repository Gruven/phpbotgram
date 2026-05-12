<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes an interval of time during which a business is open.
 *
 * Source: https://core.telegram.org/bots/api#businessopeninghoursinterval
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BusinessOpeningHoursInterval extends TelegramObject
{
  public function __construct(
    public readonly int $openingMinute,
    public readonly int $closingMinute,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
