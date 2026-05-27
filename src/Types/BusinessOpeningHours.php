<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes the opening hours of a business.
 *
 * Source: https://core.telegram.org/bots/api#businessopeninghours
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BusinessOpeningHours extends TelegramObject
{
  /**
   * @param list<BusinessOpeningHoursInterval> $openingHours
   */
  public function __construct(
    public readonly string $timeZoneName,
    public readonly array $openingHours,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
