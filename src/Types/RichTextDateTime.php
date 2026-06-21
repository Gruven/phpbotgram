<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Formatted date and time.
 *
 * Source: https://core.telegram.org/bots/api#richtextdatetime
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichTextDateTime extends RichText
{
  /**
   * @param list<array<array-key,mixed>|RichText|string>|RichText|string $text
   */
  public function __construct(
    public readonly array|RichText|string $text,
    public readonly int $unixTime,
    public readonly string $dateTimeFormat,
    public readonly string $type = 'date_time',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
