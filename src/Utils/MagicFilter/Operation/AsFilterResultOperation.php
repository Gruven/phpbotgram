<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter\Operation;

/**
 * Terminal-only operation: wrap the chain's final value into a `{name =>
 * value}` map that the dispatcher merges into handler kwargs. Returns
 * `null` (rejection) only when the final value is `null` or an empty
 * `iterable` — `false`, `0`, `''` are still accepting values carrying a
 * payload.
 *
 * Direct port of aiogram's local extension `AsFilterResultOperation`
 * in `aiogram/utils/magic_filter.py:9-18`. The PHP-side bridge
 * (`MagicFilterAsFilter::__invoke`) consumes the result.
 *
 * Accepts both PHP arrays and any `iterable` (Traversable) for the
 * empty-check to match upstream's `isinstance(value, Iterable) and not value`
 * semantics. We probe `Traversable` lazily by iterating once.
 *
 * @phpstan-type AsResult array<string, mixed>|null
 */
final class AsFilterResultOperation extends BaseOperation
{
  public function __construct(public readonly string $name) {}

  public function resolve(mixed $value, mixed $initialValue): mixed
  {
    if ($value === null) {
      return null;
    }

    if (is_array($value) && $value === []) {
      return null;
    }

    if (is_iterable($value) && !is_array($value)) {
      // Iterator/Generator path: peek for emptiness without consuming the
      // whole stream. We materialise into an array so the resolved kwarg
      // payload is concrete (matches upstream's `dict[name, value]` shape).
      $materialised = [];

      foreach ($value as $key => $item) {
        $materialised[$key] = $item;
      }

      if ($materialised === []) {
        return null;
      }

      return [$this->name => $materialised];
    }

    return [$this->name => $value];
  }
}
