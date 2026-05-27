<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Filters\F;

use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Filters\Logic\AndFilter;

/**
 * Typed F-DSL wrapper for integer Telegram fields (`Message::$messageId`,
 * `User::$id`, …). Provides the usual numeric comparators plus a
 * convenience `between($lo, $hi)` helper that composes gte+lte under an
 * `AndFilter` so users don't have to spell out the conjunction by hand.
 *
 * Mirrors the design spec § "Magic-filter runtime + F-DSL" IntField
 * surface.
 */
final class IntField extends BaseField
{
  /** Exact-equality comparator. */
  public function equals(int $value): Filter
  {
    return $this->chain->equals($value)->asFilter();
  }

  /** Strict greater-than. */
  public function gt(int $value): Filter
  {
    return $this->chain->gt($value)->asFilter();
  }

  /** Greater-than-or-equal. */
  public function gte(int $value): Filter
  {
    return $this->chain->gte($value)->asFilter();
  }

  /** Strict less-than. */
  public function lt(int $value): Filter
  {
    return $this->chain->lt($value)->asFilter();
  }

  /** Less-than-or-equal. */
  public function lte(int $value): Filter
  {
    return $this->chain->lte($value)->asFilter();
  }

  /**
   * Set-membership check across an int list. Method name follows the
   * convention from `StringField::in` for consistency.
   *
   * @param list<int> $values
   */
  public function in(array $values): Filter
  {
    return $this->chain->in_($values)->asFilter();
  }

  /**
   * Inclusive range check: accept when `$lo <= $value <= $hi`. Composes
   * two distinct chain operations under an `AndFilter` rather than a
   * single op so the cascade can short-circuit on the lower bound and
   * each child can be inspected independently by downstream tooling.
   */
  public function between(int $lo, int $hi): Filter
  {
    return new AndFilter(
      $this->chain->gte($lo)->asFilter(),
      $this->chain->lte($hi)->asFilter(),
    );
  }
}
