<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Filters\Logic;

use Gruven\PhpBotGram\Filters\Filter;

/**
 * Negation combinator. Mirrors upstream `aiogram.filters.logic._InvertFilter`
 * at `aiogram/filters/logic.py`, with one deliberate PHP-side simplification.
 *
 * Semantics: invoke the wrapped target and flip the accept/reject decision.
 *
 * - Wrapped `true`  → return `false` (accept inverts to reject).
 * - Wrapped `false` → return `true`  (reject inverts to accept).
 * - Wrapped `array` (any shape) → return `false`. An array return is
 *   semantically "accept and contribute these kwargs"; inverting an accept
 *   gives a reject. There is no array form for "negated accept" — `not`
 *   only flips booleans, and the kwargs themselves vanish (they have no
 *   meaning on the reject branch).
 *
 * Python deviation note: naive `not result` in Python would coerce a
 * populated dict to `False` (matching this implementation) but would also
 * coerce an EMPTY dict `{}` to `True` — promoting a "trivially-accepting"
 * filter into a reject under inversion. The PHP port collapses both array
 * shapes to `false` because `Filter::__invoke`'s contract never produces
 * an empty array as an "accept-without-kwargs" signal in the first place
 * (callers return `true` for that), so the only `array` returns to negate
 * are semantically accepting ones.
 */
final class InvertFilter extends Filter
{
  public function __construct(public readonly Filter $target) {}

  public function __invoke(object $event, mixed ...$kwargs): bool
  {
    // `($this->target)($event, ...$kwargs)` triggers `__invoke` on the
    // wrapped filter — the parenthesized property access disambiguates
    // from a hypothetical "call a method named `target`" interpretation.
    $result = ($this->target)($event, ...$kwargs);

    // Only an exact `false` inverts to `true`; both bare `true` and any
    // array (= accept) invert to `false`. See class docblock for why
    // we don't faithfully reproduce Python's empty-dict edge case.
    return $result === false;
  }
}
