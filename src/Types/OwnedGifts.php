<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Contains the list of gifts received and owned by a user or a chat.
 *
 * Source: https://core.telegram.org/bots/api#ownedgifts
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class OwnedGifts extends TelegramObject
{
  /**
   * @param list<OwnedGift> $gifts
   */
  public function __construct(
    public readonly int $totalCount,
    public readonly array $gifts,
    public readonly ?string $nextOffset = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
