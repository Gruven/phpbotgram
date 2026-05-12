<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * The background is a freeform gradient that rotates after every message in the chat.
 *
 * Source: https://core.telegram.org/bots/api#backgroundfillfreeformgradient
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BackgroundFillFreeformGradient extends BackgroundFill
{
  /**
   * @param list<int> $colors
   */
  public function __construct(
    public readonly string $type,
    public readonly array $colors,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
