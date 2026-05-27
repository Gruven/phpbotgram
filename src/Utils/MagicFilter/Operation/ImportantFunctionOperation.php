<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter\Operation;

/**
 * `FunctionOperation` flavour that must run even when the chain has been
 * rejected by an earlier operation. Used by `MagicFilter::__invert()` (the
 * `~F->…` negation) so a missing attribute upstream still inverts into an
 * accepting `true` rather than collapsing the verdict to `false`.
 *
 * Mirrors upstream `magic_filter.operations.function.ImportantFunctionOperation`
 * (`magic_filter/operations/function.py:31-32`).
 */
final class ImportantFunctionOperation extends FunctionOperation
{
  public function important(): bool
  {
    return true;
  }
}
