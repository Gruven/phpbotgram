<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represent a list of gifts.
 *
 * Source: https://core.telegram.org/bots/api#gifts
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class Gifts extends TelegramObject
{
  /**
   * @param list<Gift> $gifts
   */
  public function __construct(
    public readonly array $gifts,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
