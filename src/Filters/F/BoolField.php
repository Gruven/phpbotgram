<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Filters\F;

use Gruven\PhpBotGram\Filters\Filter;

/**
 * Typed F-DSL wrapper for boolean Telegram fields (`User::$isPremium`,
 * `Message::$isTopicMessage`, …). Surfaces only the two truth-value
 * comparators so call sites read as `MessageF::isTopicMessage()->isTrue()`
 * rather than `equals(true)`.
 *
 * Mirrors the design spec § "Magic-filter runtime + F-DSL" BoolField
 * surface.
 */
final class BoolField extends BaseField
{
  /**
   * Accept when the field's value is exactly `true`. The chain uses
   * MagicFilter's `==` comparator so `1`-typed payloads wouldn't be
   * accepted — but Telegram's schema only emits real booleans here.
   */
  public function isTrue(): Filter
  {
    return $this->chain->equals(true)->asFilter();
  }

  /** Mirror of `isTrue` for the falsy verdict. */
  public function isFalse(): Filter
  {
    return $this->chain->equals(false)->asFilter();
  }
}
