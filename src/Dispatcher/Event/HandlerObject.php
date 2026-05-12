<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher\Event;

use Closure;

/**
 * Wraps a handler callback together with its filter chain and metadata flags.
 * Mirrors upstream `aiogram.dispatcher.event.handler.HandlerObject`.
 *
 * The router stores one of these per registered handler. At dispatch time it
 * calls `check()` first; only if every filter votes "accept" does the
 * handler itself run (via the inherited `CallableObject::call()`). The
 * kwargs cascade described in `FilterObject` flows entirely through
 * `check()` — see the method docblock for the exact rejection ladder.
 *
 * The `flags` field is empty by default. Phase 3 Task 3.7 (Flags subsystem)
 * lands the higher-level decorators that populate it from
 * attribute-style metadata (`#[Flag('admin_only')]` etc.); HandlerObject
 * itself just stores whatever the caller hands in.
 */
final class HandlerObject extends CallableObject
{
  /**
   * @param Closure $callback The handler callable invoked once all filters
   *                          accept. Receives the dispatcher's kwargs filtered through
   *                          `prepareKwargs()` plus any entries injected by filter returns.
   * @param list<FilterObject> $filters Filters evaluated left-to-right by
   *                                    `check()`. An empty list means "always accept" — useful for catch-all
   *                                    handlers and for the root filters slot on `TelegramEventObserver`.
   * @param array<string, mixed> $flags Metadata flags attached to this
   *                                    handler. Stored verbatim and exposed read-only; middleware /
   *                                    introspection consumers read it. Phase 3 Task 3.7 will define the
   *                                    well-known keys.
   */
  public function __construct(
    Closure $callback,
    public readonly array $filters = [],
    public readonly array $flags = [],
  ) {
    parent::__construct($callback);
  }

  /**
   * Run every filter in registration order against the supplied `$args` /
   * `$kwargs`, accumulating kwargs from associative-array returns. Stops on
   * the first rejection (any falsy result — `false`, `null`, `0`, `''`,
   * `[]`).
   *
   * Returns a two-element tuple:
   *
   * - `[true, $kwargs]` — every filter accepted. `$kwargs` is the input
   *   `$kwargs` plus the merged entries from each filter that returned an
   *   array (last-wins on key collision, matching Python `dict.update()`).
   * - `[false, $kwargs]` — a filter rejected. `$kwargs` reflects the merges
   *   that happened *before* the rejecting filter (later filters never
   *   run); the rejecting filter's own return value is discarded
   *   regardless of shape because upstream's `if not check` branch returns
   *   without merging.
   *
   * Positional `$args` flow through every filter unchanged — they hold the
   * Telegram event payload in production. Falsy semantics deliberately
   * match Python's `if not check`: an empty array, an empty string, zero,
   * or `null` all vote "reject" the same as a literal `false`.
   *
   * @param array<int, mixed> $args
   * @param array<string, mixed> $kwargs
   *
   * @return array{0: bool, 1: array<string, mixed>}
   */
  public function check(array $args = [], array $kwargs = []): array
  {
    if ($this->filters === []) {
      return [true, $kwargs];
    }

    foreach ($this->filters as $filter) {
      $result = $filter->call($args, $kwargs);

      if (!$result) {
        // Falsy result (false, null, 0, '', []) = reject. Mirrors upstream
        // `if not check: return False, kwargs` — the rejecting filter's
        // payload, even if structurally a dict, is not merged.
        return [false, $kwargs];
      }

      if (is_array($result)) {
        // Truthy associative array = accept-and-merge. `[...$a, ...$b]`
        // last-wins on string keys, same as Python `kwargs.update(result)`.
        $kwargs = [...$kwargs, ...$result];
      }
      // Otherwise (true, int, non-empty string, object): accept with no
      // merge. Drops through to the next filter.
    }

    return [true, $kwargs];
  }
}
