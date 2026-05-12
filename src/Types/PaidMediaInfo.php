<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes the paid media added to a message.
 *
 * Source: https://core.telegram.org/bots/api#paidmediainfo
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PaidMediaInfo extends TelegramObject
{
  /**
   * @param list<PaidMedia> $paidMedia
   */
  public function __construct(
    public readonly int $starCount,
    public readonly array $paidMedia,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
