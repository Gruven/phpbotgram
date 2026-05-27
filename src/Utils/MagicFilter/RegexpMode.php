<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter;

/**
 * Regex-evaluation mode selector for `MagicFilter::regexp(...)`.
 *
 * Mirrors upstream `magic_filter.magic.RegexpMode`
 * (`magic_filter/magic.py:33-38`). Python upstream uses string constants;
 * we use a typed enum for type safety and PHPStan-friendliness.
 *
 * Mode semantics — PHP-side mappings since upstream relies on Python's
 * `re.Pattern` API and PHP's PCRE wrappers behave differently:
 *
 * - `MATCH`   → `preg_match($pattern, $subject)` anchored at the start of
 *   the subject (we automatically prepend `\A` when building the pattern).
 * - `FULLMATCH` → `preg_match` anchored at both ends (we wrap the pattern
 *   in `\A(?:…)\z`).
 * - `SEARCH`  → `preg_match($pattern, $subject)` un-anchored — the default
 *   PCRE behaviour.
 * - `FINDALL` → `preg_match_all` returning the list of matched strings.
 * - `FINDITER` → same as FINDALL in PHP; we materialise rather than expose
 *   an iterator because PHP's `preg_match_all` is single-shot. Functionally
 *   identical to FINDALL from the user's perspective.
 */
enum RegexpMode: string
{
  case SEARCH = 'search';
  case MATCH = 'match';
  case FINDALL = 'findall';
  case FINDITER = 'finditer';
  case FULLMATCH = 'fullmatch';
}
