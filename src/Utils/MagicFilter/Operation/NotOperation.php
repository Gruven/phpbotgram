<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter\Operation;

/**
 * Typed sentinel for the negation slot produced by `MagicFilter::not_()`.
 *
 * Behaves exactly like an `ImportantFunctionOperation` wrapping logical NOT,
 * but is a distinct class so the `~~F → F` fold can use a precise `instanceof
 * NotOperation` check rather than the fragile structural marker
 * (`$tail->args === [] && $tail->kwargs === []`). The structural check was
 * fragile: any future zero-arg `ImportantFunctionOperation` (e.g. `is_null()`)
 * would have been mis-identified as the negation sentinel and incorrectly
 * folded away by the `~~F → F` optimisation.
 *
 * Inherits `important(): bool => true` from `ImportantBaseOperation` so the
 * MagicFilter resolver always executes the NOT even after a prior rejection —
 * matching upstream `ImportantFunctionOperation` semantics.
 *
 * Usage is entirely internal to `MagicFilter::not_()`. The public surface of
 * `not_()` and `negate()` is unchanged.
 *
 * Mirrors the implicit sentinel role of the zero-arg `ImportantFunctionOperation`
 * from upstream `magic_filter/magic.py:132-139` (`__invert__`).
 *
 * @internal
 */
final class NotOperation extends FunctionOperation
{
  public function __construct()
  {
    parent::__construct(static fn(mixed $val): bool => !$val);
  }

  public function important(): bool
  {
    return true;
  }
}
