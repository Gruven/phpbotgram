<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Filters\F;

use Gruven\PhpBotGram\Filters\Filter;

/**
 * Typed F-DSL wrapper for nullable nested-object fields (`?User`,
 * `?Message`, …). Only exposes presence-tests because deeper predicates
 * are object-type-specific and belong on per-type sub-builders emitted
 * by codegen (`MessageF::fromUser()` → `NullableObjectField`).
 *
 * Users who want to drill into the nested object after a presence-test
 * compose: `Filter::all(MessageF::fromUser()->isSet(),
 * MessageF::fromUser()->id()->equals(123))` — the second filter pulls
 * a fresh chain from the typed-builder factory and applies its own
 * predicates against the nested object.
 *
 * Mirrors the design spec § "Magic-filter runtime + F-DSL"
 * NullableObjectField<T> surface (the generic parameter is documented in
 * the spec but not expressible in PHP's type system — the underlying
 * chain is `mixed` until codegen specializes the wrappers).
 */
final class NullableObjectField extends BaseField
{
  /** Accept when the nested object is non-null. */
  public function isSet(): Filter
  {
    return $this->chain->notEquals(null)->asFilter();
  }

  /** Accept when the nested object is null. */
  public function isNull(): Filter
  {
    return $this->chain->equals(null)->asFilter();
  }
}
