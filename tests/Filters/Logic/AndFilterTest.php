<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Filters\Logic;

use Closure;
use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Filters\Logic\AndFilter;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\TelegramObject;
use PHPUnit\Framework\TestCase;

/**
 * Upstream `tests/test_filters/test_logic.py` cases deliberately not ported:
 *
 * - `TestAndFilter::test_dunder_methods` — Python overloads `__or__`, `__and__`, `__invert__`
 *   on filter instances to build combinator chains (e.g. `f1 & f2`). PHP cannot reuse
 *   bitwise/arithmetic operators between arbitrary objects; the port uses named factory
 *   methods `Filter::all()`, `Filter::any()`, `Filter::invertOf()` instead (reason 3).
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 */
final class AndFilterTest extends TestCase
{
  public function testEmptyAndFilterAcceptsWithTrue(): void
  {
    // Upstream: `for target in self.targets:` over an empty tuple makes the
    // loop a no-op, so `final_result == {}` falls into the `return True`
    // branch. PHP mirrors that — empty AndFilter is the identity-accept.
    $filter = new AndFilter();

    self::assertTrue($filter($this->event()));
  }

  public function testAllTargetsReturnTrueResultsInTrue(): void
  {
    // Every child votes `true` with no kwargs to contribute. Upstream's
    // `final_result` stays `{}`, so the combinator returns `True` (not an
    // empty dict). Verifying the boolean fast-path here.
    $filter = new AndFilter(
      $this->filter(static fn(): bool => true),
      $this->filter(static fn(): bool => true),
    );

    self::assertTrue($filter($this->event()));
  }

  public function testFalseShortCircuitsAndRejectsImmediately(): void
  {
    // `if not result: return False` — the rejection ladder. The third
    // filter must NOT run; capture that via a flag closure to assert
    // short-circuit semantics rather than just the return value.
    $thirdRan = false;
    $filter = new AndFilter(
      $this->filter(static fn(): bool => true),
      $this->filter(static fn(): bool => false),
      $this->filter(static function () use (&$thirdRan): bool {
        $thirdRan = true;

        return true;
      }),
    );

    self::assertFalse($filter($this->event()));
    self::assertFalse($thirdRan, 'AndFilter must short-circuit after a false vote.');
  }

  public function testMixingTrueAndArrayReturnsTheMergedArray(): void
  {
    // Upstream `isinstance(result, dict): final_result.update(result)` —
    // plain `true` votes accept without contributing kwargs; the array
    // vote contributes its entries. Final result is the accumulated map.
    $filter = new AndFilter(
      $this->filter(static fn(): bool => true),
      $this->filter(static fn(): array => ['command' => 'start']),
      $this->filter(static fn(): bool => true),
    );

    self::assertSame(['command' => 'start'], $filter($this->event()));
  }

  public function testTwoArraysMergeWithLastWriteWins(): void
  {
    // PHP `[...$a, ...$b]` matches Python `dict.update()` semantics —
    // later keys overwrite earlier ones on collision. Filter 2 wins the
    // `key` slot; the disjoint `extra` from filter 1 still survives.
    $filter = new AndFilter(
      $this->filter(static fn(): array => ['key' => 'first', 'extra' => 'preserved']),
      $this->filter(static fn(): array => ['key' => 'second']),
    );

    self::assertSame(
      ['key' => 'second', 'extra' => 'preserved'],
      $filter($this->event()),
    );
  }

  public function testFirstDictSecondFalseReturnsFalse(): void
  {
    // Upstream parametrize row 4: `and_f(lambda t: {"t": t}, lambda t: t is
    // False)` against `True` → `False`. When the FIRST filter accepts with a
    // dict but the SECOND rejects (returns false), the combinator must still
    // return false — the second filter's rejection wins regardless of any
    // kwargs the first contributed. Mirrors upstream `if not result: return
    // False` evaluated AFTER the first dict has been accumulated.
    $filter = new AndFilter(
      $this->filter(static fn(): array => ['t' => true]),
      $this->filter(static fn(): bool => false),
    );

    self::assertFalse($filter($this->event()));
  }

  public function testKwargCascadeForwardsEarlierFilterReturnsIntoLaterFilters(): void
  {
    // The hallmark AndFilter feature: filter N sees the kwargs filter
    // N-1 contributed (mirrors upstream `**kwargs, **final_kwargs` at
    // the recursive `target(...)` call). The captured map proves filter
    // 2 actually received the cascade. Initial `$kwargs` ['outer' => 'x']
    // is preserved alongside filter 1's `cmd` injection.
    $received = null;
    $filter = new AndFilter(
      $this->filter(static fn(object $e, array $kwargs): array => ['cmd' => 'start']),
      $this->filter(static function (object $e, array $kwargs) use (&$received): bool {
        $received = $kwargs;

        return true;
      }),
    );

    // Spread `['outer' => 'x']` so the string key becomes a named argument
    // and the variadic `...$kwargs` in each child's `__invoke` captures it
    // as `$kwargs['outer']` rather than `$kwargs[0]`.
    $result = $filter($this->event(), ...['outer' => 'x']);

    self::assertSame(['cmd' => 'start'], $result);
    self::assertSame(['outer' => 'x', 'cmd' => 'start'], $received);
  }

  /**
   * Anonymous-class filter helper that delegates to a closure. Keeps each
   * fixture concise without polluting the test file with named subclasses.
   *
   * @param Closure(object, array<int|string, mixed>): (array<string, mixed>|bool) $vote
   */
  private function filter(Closure $vote): Filter
  {
    return new class ($vote) extends Filter {
      /** @param Closure(object, array<int|string, mixed>): (array<string, mixed>|bool) $vote */
      public function __construct(private readonly Closure $vote) {}

      public function __invoke(object $event, mixed ...$kwargs): array|bool
      {
        return ($this->vote)($event, $kwargs);
      }
    };
  }

  private function event(): TelegramObject
  {
    return new Chat(id: 1, type: 'private');
  }
}
