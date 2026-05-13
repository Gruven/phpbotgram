<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter\Operation;

use Closure;

/**
 * Reverse-binary combinator: the running value is on the RIGHT and a
 * fixed literal (or nested `MagicFilter`) is on the LEFT. Powers the
 * `__rxxx__` family of upstream operators — for example `'pong' + F.text`
 * produces an `RCombinationOperation(left='pong', combinator=add)` whose
 * `resolve(value)` returns `'pong' . $value`.
 *
 * Mirrors upstream `magic_filter.operations.combination.RCombinationOperation`
 * (`magic_filter/operations/combination.py:25-37`).
 */
final class RCombinationOperation extends BaseOperation
{
  public function __construct(
    public readonly mixed $left,
    public readonly Closure $combinator,
  ) {}

  public function resolve(mixed $value, mixed $initialValue): mixed
  {
    return ($this->combinator)(
      Helper::resolveIfNeeded($this->left, $initialValue),
      $value,
    );
  }
}
