<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter\Operation;

use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilter;

/**
 * Filter an iterable subject through an inner `MagicFilter`, keeping only
 * the items for which the inner accepts.
 *
 * Mirrors upstream `magic_filter.operations.extract.ExtractOperation`
 * (`magic_filter/operations/extract.py`). Returns `null` for non-iterable
 * values (matching upstream's `if not isinstance(value, Iterable): return None`)
 * so the resolver doesn't blow up on type mismatches; the calling chain
 * can `as_()` the `null` into a reject or keep going.
 *
 * @return null|list<mixed>
 */
final class ExtractOperation extends BaseOperation
{
  public function __construct(public readonly MagicFilter $extractor) {}

  public function resolve(mixed $value, mixed $initialValue): mixed
  {
    if (!is_iterable($value)) {
      return null;
    }

    $result = [];

    foreach ($value as $item) {
      if ($this->extractor->resolve($item)) {
        $result[] = $item;
      }
    }

    return $result;
  }
}
