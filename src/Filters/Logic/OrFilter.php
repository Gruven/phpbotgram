<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Filters\Logic;

use Gruven\PhpBotGram\Filters\Filter;

/**
 * "At least one child must accept" combinator. Mirrors upstream
 * `aiogram.filters.logic._OrFilter` at `aiogram/filters/logic.py`.
 *
 * Semantics:
 *
 * 1. With zero targets, the loop is a no-op and the filter returns
 *    `false` (identity-reject — the dual of empty AndFilter).
 * 2. Each child sees the SAME input `$kwargs` (no cascade — unlike
 *    AndFilter). Upstream calls `target(*args, **kwargs)` once per
 *    iteration without merging anything from the previous result.
 * 3. The first non-`false` return (`true` or an array) is forwarded
 *    verbatim to the caller — including array form, so a `Regex |
 *    Command` chain can still inject match data when either branch
 *    accepts. Later targets are skipped.
 * 4. If every target rejects, return `false`.
 */
final class OrFilter extends Filter
{
  /** @var list<Filter> */
  public readonly array $targets;

  public function __construct(Filter ...$targets)
  {
    $this->targets = array_values($targets);
  }

  public function __invoke(object $event, mixed ...$kwargs): array|bool
  {
    foreach ($this->targets as $target) {
      $result = $target($event, ...$kwargs);

      if ($result !== false) {
        // First accept (true or array) wins outright; later targets
        // are skipped, matching upstream's `if result: return result`.
        return $result;
      }
    }

    return false;
  }
}
