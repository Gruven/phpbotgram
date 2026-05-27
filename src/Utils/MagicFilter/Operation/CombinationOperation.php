<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter\Operation;

use Closure;

/**
 * Binary combinator between the running value (left operand) and either a
 * literal or another `MagicFilter` chain (right operand). Powers AND, XOR,
 * arithmetic combinations, etc.
 *
 * Mirrors upstream `magic_filter.operations.combination.CombinationOperation`
 * (`magic_filter/operations/combination.py`). The right operand is resolved
 * lazily — if it's a `MagicFilter` it gets evaluated against the chain's
 * original subject by `Helper::resolveIfNeeded` (so `F->a & F->b` does the
 * right thing).
 *
 * Logical AND uses a "truthy-and" combinator (`a && b`) so it returns the
 * second operand when the first is truthy and the first when it's falsy —
 * the standard short-circuit. Pure-boolean callers can wrap each side in
 * `equals(…)` to coerce to `bool` upstream.
 */
class CombinationOperation extends BaseOperation
{
  public function __construct(
    public readonly mixed $right,
    public readonly Closure $combinator,
  ) {}

  public function resolve(mixed $value, mixed $initialValue): mixed
  {
    return ($this->combinator)(
      $value,
      Helper::resolveIfNeeded($this->right, $initialValue),
    );
  }
}
