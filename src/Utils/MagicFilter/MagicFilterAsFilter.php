<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter;

use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Utils\MagicFilter\Exception\RejectOperations;

/**
 * Bridge that turns a `MagicFilter` chain into a dispatcher-consumable
 * `Filter`. Mirrors the upstream `MagicFilter` → `Filter` adapter
 * behaviour: aiogram passes a `MagicFilter` instance straight where a
 * `Filter` is expected and the dispatcher's `_check` wraps it via
 * `MagicFilter.resolve`. The PHP `Filter` abstract is more rigid
 * (`__invoke(object, array)`); this bridge implements that
 * contract.
 *
 * Acceptance contract — matches the rules documented in the spec
 * (Layer 1, `Utils\MagicFilter\MagicFilter`):
 *
 * - Chain raised `RejectOperations` mid-walk → return `false`.
 * - Final value is `null` → return `false`.
 * - Final value is an `array<string, mixed>` with at least one entry →
 *   return the array verbatim (the dispatcher merges it into handler
 *   kwargs).
 * - Final value is an empty array → return `false` (matches the
 *   "empty iterable rejects" rule from upstream's
 *   `AsFilterResultOperation`).
 * - Any other value → coerce to `bool` and return.
 */
final class MagicFilterAsFilter extends Filter
{
  public function __construct(public readonly MagicFilter $magic) {}

  public function __invoke(object $event, array $kwargs = []): array|bool
  {
    try {
      $result = $this->magic->resolve($event);
    } catch (RejectOperations) {
      // A rejection that escapes resolve() (e.g. from a non-important
      // operation that nothing rescued) collapses to a hard reject.
      return false;
    }

    if ($result === null) {
      return false;
    }

    if (is_array($result)) {
      if ($result === []) {
        return false;
      }

      // Detect kwarg-shaped arrays (string keys) vs payload arrays. Only
      // kwarg-shaped arrays propagate as handler kwargs; numeric-keyed
      // results coerce to bool (truthy = accept). This matches the
      // upstream contract where `AsFilterResultOperation` produces a
      // `{name: value}` dict and the dispatcher detects "dict means
      // kwargs to merge".
      $stringKeyed = [];

      foreach ($result as $key => $value) {
        if (is_string($key)) {
          $stringKeyed[$key] = $value;
        }
      }

      if ($stringKeyed !== []) {
        return $stringKeyed;
      }

      return true;
    }

    return (bool)$result;
  }
}
