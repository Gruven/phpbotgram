<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Filters;

use Gruven\PhpBotGram\Types\ErrorEvent;

/**
 * Filter that accepts an `ErrorEvent` whose `->exception->getMessage()`
 * matches a regex pattern; on match, returns the match metadata as kwargs
 * so the dispatcher merges them into the handler invocation.
 *
 * Port of `aiogram.filters.exception.ExceptionMessageFilter`
 * (`aiogram/filters/exception.py:30-57`).
 *
 * # Pattern format
 *
 * `$pattern` is a PCRE pattern STRING WITH DELIMITERS — the form
 * `preg_match` consumes directly, e.g. `'/^api error: (.*)$/'`. Upstream
 * accepts either a string or a precompiled `re.Pattern`; PHP has no
 * standalone pattern object so the string form is the only option. The
 * pattern is held verbatim on a readonly property so debuggers and
 * potential future `__toString` introspection can recover it.
 *
 * # Return shape
 *
 * On accept the filter returns:
 *
 *     [
 *         'match'  => string, // the full matched substring ($matches[0])
 *         'groups' => list<string>, // numbered capture groups ($matches[1..])
 *     ]
 *
 * Deviates from upstream's `{'match_exception': re.Match}` because PHP has
 * no `re.Match` analogue; we surface the most useful pieces (matched text
 * + captured groups) under stable kwarg names instead. Handlers that
 * declare an `$match` and/or `$groups` parameter receive the values
 * directly via the dispatcher's named-kwarg binding.
 *
 * The match is performed against `getMessage()` (the throwable's textual
 * message), not against the class name or the trace. Mirrors upstream's
 * `self.pattern.match(str(event.exception))` where `str(exception)` is
 * Python's textual representation — for our PHP port the closest analogue
 * is the human-readable message.
 */
final class ExceptionMessageFilter extends Filter
{
  /**
   * @param string $pattern PCRE pattern STRING with delimiters (e.g.
   *                        `'/boom/'`). Held verbatim — no compilation step because PHP's
   *                        `preg_match` caches compiled patterns internally per process.
   */
  public function __construct(public readonly string $pattern) {}

  /**
   * Match the registered pattern against `event->exception->getMessage()`.
   *
   * @param array<string, mixed> $kwargs Unused — the filter is event-only.
   *
   * @return array{match: string, groups: list<string>}|false On match,
   *                                                          a kwargs dict for the dispatcher to merge. On miss or
   *                                                          non-`ErrorEvent` event, `false`.
   */
  public function __invoke(object $event, array $kwargs = []): array|false
  {
    if (!$event instanceof ErrorEvent) {
      // Defensive type guard. A misconfigured router could wire this
      // filter to a non-errors observer; rejecting silently is safer
      // than crashing on `->exception->getMessage()` indirection.
      return false;
    }

    $message = $event->exception->getMessage();
    $matches = [];

    // `preg_match` returns 1 on match, 0 on no match, false on regex
    // compilation/runtime error. Treat any non-1 outcome as reject — a
    // malformed pattern is the caller's bug to surface and we don't want
    // the dispatcher's error pipeline to itself throw mid-dispatch.
    if (preg_match($this->pattern, $message, $matches) !== 1) {
      return false;
    }

    /** @var list<string> $groups */
    $groups = array_values(array_slice($matches, 1));

    return [
      // Full matched substring — equivalent to `re.Match.group(0)` /
      // `match[0]` in Python. Most handlers want the matched text more
      // than the per-group breakdown, so it's exposed as a top-level
      // `match` kwarg for ergonomic access.
      'match' => $matches[0],
      // Numbered capture groups (excluding group 0). `array_slice` from
      // index 1 mirrors `match.groups()` in Python's `re` module. Empty
      // list when the pattern has no capturing parentheses.
      'groups' => $groups,
    ];
  }
}
