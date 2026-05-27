<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Filters;

use Gruven\PhpBotGram\Filters\ExceptionMessageFilter;
use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\ErrorEvent;
use Gruven\PhpBotGram\Types\Update;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * Coverage for `ExceptionMessageFilter` — port of
 * `aiogram.filters.exception.ExceptionMessageFilter`
 * (`aiogram/filters/exception.py:30-57`).
 *
 * The filter accepts an `ErrorEvent` whose `->exception->getMessage()`
 * matches a regex pattern. On match it returns the match metadata
 * (`['match' => string, 'groups' => list<string>]`) as kwargs that the
 * dispatcher merges into the handler invocation.
 *
 * Spec deviations from upstream — documented in the class docblock:
 *
 *   - `pattern` is a PCRE pattern STRING with delimiters (e.g. `'/^err: (.*)$/'`),
 *     not a precompiled `re.Pattern`. PHP's `preg_match` takes the pattern
 *     string directly; there is no equivalent of `re.compile` to cache.
 *   - The return shape is `['match' => ..., 'groups' => ...]` rather than
 *     upstream's `{'match_exception': re.Match}` — PHP doesn't expose a
 *     match object, so we surface the raw match string + numbered groups
 *     instead. The upstream `match_exception` kwarg surface is preserved
 *     as the `match` entry's value (the matched substring), which is the
 *     most useful piece of data on the PHP side.
 *
 * Upstream `tests/test_filters/test_exception.py` cases deliberately not ported:
 *
 * - `TestExceptionMessageFilter::test_converter` precompiled-pattern row (`re.compile(...)`) —
 *   Python's `re.compile` returns a precompiled pattern object; PHP has no equivalent type.
 *   The PHP port accepts pattern strings with PCRE delimiters only (reason: no `re.compile`
 *   equivalent in PHP).
 * - `TestExceptionMessageFilter::test_str` — `Filter` and DTOs have no `__str__` / `__repr__`
 *   equivalents in the PHP port (reason 5).
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 *
 * @internal
 *
 * @coversNothing
 */
final class ExceptionMessageFilterTest extends TestCase
{
  public function testIsAFilterSubclass(): void
  {
    // Smoke-check inheritance: dispatcher routing + Logic combinators
    // rely on every concrete filter being a `Filter`.
    self::assertInstanceOf(Filter::class, new ExceptionMessageFilter('/.*/'));
  }

  public function testStoresPatternAsReadonlyProperty(): void
  {
    // The pattern is held verbatim — debuggers, signature dumps, and
    // potential future `Stringable` introspection rely on the original
    // pattern string being recoverable.
    $filter = new ExceptionMessageFilter('/boom/');

    self::assertSame('/boom/', $filter->pattern);
  }

  public function testAcceptsErrorEventWhenPatternMatches(): void
  {
    // The smallest happy path: the pattern matches the exception message
    // → returns a `['match' => ..., 'groups' => []]` accept-with-kwargs.
    $filter = new ExceptionMessageFilter('/boom/');

    $result = $filter($this->errorEvent(new RuntimeException('boom!')));

    self::assertIsArray($result);
    self::assertArrayHasKey('match', $result);
    self::assertSame('boom', $result['match']);
    self::assertSame([], $result['groups']);
  }

  public function testReturnsCaptureGroupsAsKwargs(): void
  {
    // The PHP port surfaces numbered capture groups under `'groups'` as
    // a list of strings (mirroring upstream's `match.groups()`). Verify
    // the list ordering matches the regex parenthesis ordering.
    $filter = new ExceptionMessageFilter('/^err (\w+): (.+)$/');

    $result = $filter($this->errorEvent(new RuntimeException('err CODE: details here')));

    self::assertIsArray($result);
    self::assertSame('err CODE: details here', $result['match']);
    self::assertSame(['CODE', 'details here'], $result['groups']);
  }

  public function testRejectsErrorEventWhenPatternDoesNotMatch(): void
  {
    // No match → return false. Mirrors upstream's `if not result: return False`.
    $filter = new ExceptionMessageFilter('/^expected$/');

    self::assertFalse($filter($this->errorEvent(new RuntimeException('actual'))));
  }

  public function testRejectsNonErrorEventEvents(): void
  {
    // Defensive type guard mirroring `ExceptionTypeFilter` — a
    // misconfigured router could wire this filter to a non-errors
    // observer. Reject rather than crash on `->getMessage()` indirection.
    $filter = new ExceptionMessageFilter('/.*/');

    self::assertFalse($filter(new Update(updateId: 1)));
    self::assertFalse($filter(new Chat(id: 1, type: 'private')));
  }

  public function testMatchesAgainstExceptionMessageNotClassName(): void
  {
    // Sanity check: the regex is applied to `getMessage()`, not the
    // exception class name or any other property. A pattern that would
    // match the class name but not the message must REJECT.
    $filter = new ExceptionMessageFilter('/RuntimeException/');

    self::assertFalse($filter($this->errorEvent(new RuntimeException('different message'))));
  }

  public function testPatternIsAnchoredAtStartLikePythonReMatch(): void
  {
    // Upstream-parity anchoring. Python's `re.Pattern.match` is anchored
    // at position 0 of the string — `re.compile('error').match('foo error bar')`
    // returns `None`. PHP's `preg_match` without the `A` modifier is
    // unanchored and would find `'error'` at position 4. We add the PCRE
    // `A` modifier to restore start-of-string anchoring.
    //
    // Upstream ref: `aiogram/filters/exception.py:54`
    //   `result = self.pattern.match(str(cast(ErrorEvent, obj).exception))`
    $filter = new ExceptionMessageFilter('/error/');

    // MUST reject: 'error' appears mid-string, not at position 0.
    self::assertFalse($filter($this->errorEvent(new RuntimeException('foo error bar'))));

    // MUST accept: 'error' is at position 0.
    self::assertIsArray($filter($this->errorEvent(new RuntimeException('error: something went wrong'))));
  }

  /**
   * Build an `ErrorEvent` wrapping a given exception. The filter only
   * touches `->exception->getMessage()` so a placeholder `Update` is fine.
   */
  private function errorEvent(Throwable $e): ErrorEvent
  {
    return new ErrorEvent(new Update(updateId: 1), $e);
  }
}
