<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Filters\F;

use Gruven\PhpBotGram\Filters\Filter;

/**
 * Typed F-DSL wrapper for nullable string fields (`?string` — most of the
 * optional text fields on `Message`, `User`, …). Mirrors the `StringField`
 * comparator surface and adds two presence-tests (`isSet` / `isNull`) so
 * callers can express null-checks without dropping into raw MagicFilter.
 *
 * Mirrors the design spec § "Magic-filter runtime + F-DSL"
 * NullableStringField surface.
 */
final class NullableStringField extends BaseField
{
  /**
   * Accept when the field is non-null. Implemented via `notEquals(null)`
   * so the chain treats the field's actual value as the running value
   * and produces a clean bool verdict.
   */
  public function isSet(): Filter
  {
    return $this->chain->notEquals(null)->asFilter();
  }

  /** Mirror of `isSet`: accept when the field is null. */
  public function isNull(): Filter
  {
    return $this->chain->equals(null)->asFilter();
  }

  /** Exact-match comparator. Reject when the field is null. */
  public function equals(string $value): Filter
  {
    return $this->chain->equals($value)->asFilter();
  }

  /** Substring check. Reject when the field is null. */
  public function contains(string $needle): Filter
  {
    return $this->chain->contains($needle)->asFilter();
  }

  /** Prefix check. Reject when the field is null. */
  public function startsWith(string $prefix): Filter
  {
    return $this->chain->startsWith($prefix)->asFilter();
  }

  /** Suffix check. Reject when the field is null. */
  public function endsWith(string $suffix): Filter
  {
    return $this->chain->endsWith($suffix)->asFilter();
  }

  /**
   * Set-membership across a string list. Reject when the field is null.
   *
   * @param list<string> $values
   */
  public function in(array $values): Filter
  {
    return $this->chain->in_($values)->asFilter();
  }
}
