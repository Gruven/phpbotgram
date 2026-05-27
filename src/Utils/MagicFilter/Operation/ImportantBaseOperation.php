<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter\Operation;

/**
 * Marker subclass: operations that must always evaluate even when a
 * previous operation in the chain raised `RejectOperations`. The
 * `MagicFilter::_resolve` loop checks `important()` after a rejection
 * and only executes operations that opt in via this base.
 *
 * Mirrors upstream's `BaseOperation.important = True` class attribute
 * (see `ImportantCombinationOperation`, `ImportantFunctionOperation` in
 * `magic_filter/operations/combination.py`, `function.py`).
 *
 * Subclasses do not need to override anything else — they just extend
 * this class instead of `BaseOperation` and the `important()` method
 * inherited from here flips to `true`.
 */
abstract class ImportantBaseOperation extends BaseOperation
{
  public function important(): bool
  {
    return true;
  }
}
