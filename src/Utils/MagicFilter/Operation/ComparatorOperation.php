<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter\Operation;

use Closure;

/**
 * Binary comparison between the running value and a literal (or another
 * `MagicFilter` chain that resolves against the same root): supports
 * `==`, `!=`, `<`, `<=`, `>`, `>=` via a pluggable comparator closure.
 *
 * Mirrors upstream `magic_filter.operations.comparator.ComparatorOperation`
 * (`magic_filter/operations/comparator.py`). Upstream stores the comparator
 * as a Python `operator.eq` / `operator.lt` / etc. callable; we use named
 * `Closure` instances built once in `MagicFilter::__construct` to keep the
 * dispatch table cheap.
 *
 * The right operand is resolved lazily via `Helper::resolveIfNeeded` so
 * call sites like `F->id == F->reply->fromUser->id` work (both sides are
 * MagicFilter chains rooted on the same subject).
 */
final class ComparatorOperation extends BaseOperation
{
  public function __construct(
    public readonly mixed $right,
    public readonly Closure $comparator,
  ) {}

  public function resolve(mixed $value, mixed $initialValue): mixed
  {
    return ($this->comparator)(
      $value,
      Helper::resolveIfNeeded($this->right, $initialValue),
    );
  }
}
