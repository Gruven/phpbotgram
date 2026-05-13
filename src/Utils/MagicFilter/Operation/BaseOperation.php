<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter\Operation;

/**
 * Single step in a MagicFilter chain. Each operation is invoked with the
 * value the previous operation produced AND the original subject (the
 * "initial value") so that combinator-style operations can resolve nested
 * `MagicFilter` references against the same root.
 *
 * Direct port of upstream `magic_filter.operations.base.BaseOperation`
 * (`magic_filter/operations/base.py`).
 *
 * The `important()` accessor mirrors upstream's class-level `important:
 * bool` attribute. The resolver in `MagicFilter::_resolve` consults it
 * to decide whether a "rejected" chain should keep walking: non-important
 * operations are skipped on rejection (their `$value` becomes `null`),
 * important ones always run so a `NOT` / `OR` short-circuit can still
 * rescue the chain.
 */
abstract class BaseOperation
{
  /**
   * `true` for operations that must always evaluate even when an earlier
   * step in the chain raised `RejectOperations`. The canonical example is
   * `~F->message->text` (`ImportantFunctionOperation` wrapping logical
   * NOT): if `text` is missing we want the negation to still flip the
   * `null` result to `true` rather than collapse to `false`.
   *
   * Subclasses opt in by extending `ImportantBaseOperation` or by
   * overriding this method directly.
   */
  public function important(): bool
  {
    return false;
  }

  /**
   * Evaluate this operation.
   *
   * @param mixed $value The current running value (output of the
   *                     previous operation, or the original subject
   *                     for the first step).
   * @param mixed $initialValue The original subject passed to
   *                            `MagicFilter::resolve`. Used by combinator
   *                            / comparator operations that need to
   *                            resolve a nested `MagicFilter` against the
   *                            root rather than the current intermediate
   *                            value.
   */
  abstract public function resolve(mixed $value, mixed $initialValue): mixed;
}
