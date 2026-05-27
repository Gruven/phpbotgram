<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter\Operation;

use Closure;
use Gruven\PhpBotGram\Utils\MagicFilter\Exception\RejectOperations;
use Throwable;

/**
 * Apply an external callable to the running value, optionally with extra
 * positional / named arguments resolved against the chain root.
 *
 * Mirrors upstream `magic_filter.operations.function.FunctionOperation`
 * (`magic_filter/operations/function.py`). Call semantics:
 *
 * - `args` are prepended to the value: `function(...$args, $value)`.
 *   This matches upstream which calls `function(*args, value, **kwargs)`.
 * - `kwargs` are passed as named arguments.
 * - Each `arg`/`kwarg` entry that is itself a `MagicFilter` is resolved
 *   against the original subject before the call, courtesy of
 *   `Helper::resolveIfNeeded` — so call sites like
 *   `F->func(InArrayCheck, ['admin', 'mod'])` work.
 * - Any `TypeError`/`ValueError` from the underlying call is wrapped as
 *   `RejectOperations` so the chain short-circuits (matching upstream
 *   behaviour at `function.py:27`).
 */
class FunctionOperation extends BaseOperation
{
  /**
   * @param list<mixed> $args
   * @param array<string, mixed> $kwargs
   */
  public function __construct(
    public readonly Closure $function,
    public readonly array $args = [],
    public readonly array $kwargs = [],
  ) {}

  public function resolve(mixed $value, mixed $initialValue): mixed
  {
    $resolvedArgs = [];

    foreach ($this->args as $arg) {
      $resolvedArgs[] = Helper::resolveIfNeeded($arg, $initialValue);
    }
    // Upstream injects the running `value` immediately after the prepended
    // positional args. Replicating that order is critical for predicates
    // like `in_op(haystack, value) -> bool`.
    $resolvedArgs[] = $value;

    $resolvedKwargs = [];

    foreach ($this->kwargs as $key => $kwarg) {
      $resolvedKwargs[$key] = Helper::resolveIfNeeded($kwarg, $initialValue);
    }

    try {
      return ($this->function)(...$resolvedArgs, ...$resolvedKwargs);
    } catch (Throwable $e) {
      // Same as upstream `function.py:27-28`: TypeError/ValueError are the
      // expected "wrong shape" failures — we treat them as soft rejections.
      // Other exceptions also become rejections so the chain stays
      // composable; an unexpected error from user code shouldn't abort
      // the dispatcher's filter loop.
      throw new RejectOperations($e);
    }
  }
}
