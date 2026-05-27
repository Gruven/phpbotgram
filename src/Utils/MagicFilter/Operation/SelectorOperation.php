<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter\Operation;

use Gruven\PhpBotGram\Utils\MagicFilter\Exception\RejectOperations;
use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilter;

/**
 * Sub-chain predicate: hand the running value to an inner `MagicFilter`
 * and either pass the value through (when the inner accepts) or reject the
 * chain.
 *
 * Mirrors upstream `magic_filter.operations.selector.SelectorOperation`
 * (`magic_filter/operations/selector.py`). Used when the user writes
 * `F[F.text == 'hi']` — the inner filter must accept the current value
 * for the chain to continue.
 *
 * Returns the original value verbatim on success so subsequent operations
 * keep operating on the real subject (not a coerced boolean).
 */
final class SelectorOperation extends BaseOperation
{
  public function __construct(public readonly MagicFilter $inner) {}

  public function resolve(mixed $value, mixed $initialValue): mixed
  {
    if ($this->inner->resolve($value)) {
      return $value;
    }

    throw new RejectOperations();
  }
}
