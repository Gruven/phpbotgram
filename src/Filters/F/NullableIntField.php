<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Filters\F;

use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Filters\Logic\AndFilter;

/**
 * Typed F-DSL wrapper for nullable int fields (`?int` — e.g.
 * `Message::$messageThreadId`, `User::$senderBoostCount`). Mirrors the
 * `IntField` comparator surface and adds `isSet` / `isNull` presence
 * tests.
 *
 * Mirrors the design spec § "Magic-filter runtime + F-DSL"
 * NullableIntField surface.
 */
final class NullableIntField extends BaseField
{
  /** Accept when the field is non-null. */
  public function isSet(): Filter
  {
    return $this->chain->notEquals(null)->asFilter();
  }

  /** Accept when the field is null. */
  public function isNull(): Filter
  {
    return $this->chain->equals(null)->asFilter();
  }

  /** Exact-match comparator. Reject when the field is null. */
  public function equals(int $value): Filter
  {
    return $this->chain->equals($value)->asFilter();
  }

  /** Strict greater-than. Reject when the field is null. */
  public function gt(int $value): Filter
  {
    return $this->chain->gt($value)->asFilter();
  }

  /** Greater-than-or-equal. Reject when the field is null. */
  public function gte(int $value): Filter
  {
    return $this->chain->gte($value)->asFilter();
  }

  /** Strict less-than. Reject when the field is null. */
  public function lt(int $value): Filter
  {
    return $this->chain->lt($value)->asFilter();
  }

  /** Less-than-or-equal. Reject when the field is null. */
  public function lte(int $value): Filter
  {
    return $this->chain->lte($value)->asFilter();
  }

  /**
   * Set-membership across an int list. Reject when the field is null.
   *
   * @param list<int> $values
   */
  public function in(array $values): Filter
  {
    return $this->chain->in_($values)->asFilter();
  }

  /**
   * Inclusive range check. Reject when the field is null. Same
   * gte+lte composition under an AndFilter as IntField::between.
   */
  public function between(int $lo, int $hi): Filter
  {
    return new AndFilter(
      $this->chain->gte($lo)->asFilter(),
      $this->chain->lte($hi)->asFilter(),
    );
  }
}
