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
    public readonly array $colors,
    public readonly string $type = 'freeform_gradient',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
