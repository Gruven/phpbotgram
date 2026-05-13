<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Filters\F;

use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\FunctionOperation;

/**
 * Typed F-DSL wrapper for PCRE-pattern matching over a string-valued
 * chain. The match operation reads the running string off the chain,
 * runs `preg_match` against it, and either rejects (no match) or accepts
 * — optionally injecting the match payload as kwargs.
 *
 * Two output modes:
 *
 * 1. `captureGroups: false` (default) — bool verdict. The Filter accepts
 *    when the pattern matches and rejects otherwise; no kwargs are
 *    injected into the dispatcher.
 *
 * 2. `captureGroups: true` — kwarg injection. On a successful match the
 *    Filter emits `['regexp_match' => $matches[0], 'regexp_groups' =>
 *    $matches]` so handlers can destructure named and numbered captures.
 *    The dict keys mirror aiogram's regex-filter contract (see spec
 *    § "Magic-filter runtime + F-DSL"); `regexp_match` is the full
 *    matched substring and `regexp_groups` is the full `preg_match`
 *    output array (group 0 plus numbered/named captures).
 *
 * The PCRE pattern is wrapped with `#…#u` delimiters internally so users
 * pass naked patterns — matching the convention `MagicFilter::regexp()`
 * already uses. The `\A` anchor is implicit (match from the start of the
 * string) for parity with Python `re.match`.
 */
final class RegexField extends BaseField
{
  /**
   * Wrap the chain's string output in a `preg_match` predicate.
   *
   * @param bool $captureGroups When `true`, emit a `{regexp_match,
   *                            regexp_groups}` kwarg payload on accept.
   *                            When `false`, surface a plain bool.
   */
  public function matches(string $pattern, bool $captureGroups = false): Filter
  {
    if (!$captureGroups) {
      // Simple bool branch: reuse MagicFilter's `regexp` op which
      // returns the match array on hit and null on miss — the bridge
      // collapses both into the expected bool verdict.
      return $this->chain->regexp($pattern)->asFilter();
    }

    // Capture-groups branch: append a single FunctionOperation that
    // runs preg_match directly and returns either a kwarg-shaped dict
    // (`['regexp_match' => ..., 'regexp_groups' => ...]`) on match or
    // null on miss. The bridge maps null → false (reject) and the
    // string-keyed array → kwarg injection.
    $operation = new FunctionOperation(
      static function (string $rawPattern, mixed $value): ?array {
        if (!is_string($value)) {
          return null;
        }

        // Escape any `#` in the user's pattern so we can safely wrap
        // with `#…#u` delimiters — same trick MagicFilter::regexp uses.
        $delimited = '#\\A' . str_replace('#', '\\#', $rawPattern) . '#u';
        $matches = [];
        $result = @preg_match($delimited, $value, $matches);

        if ($result !== 1) {
          return null;
        }

        return [
          'regexp_match' => $matches[0],
          'regexp_groups' => $matches,
        ];
      },
      [$pattern],
    );

    return $this->chain->extendWith($operation)->asFilter();
  }
}
