<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Filters\Logic;

use Closure;
use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Filters\Logic\OrFilter;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\TelegramObject;
use PHPUnit\Framework\TestCase;

/**
 * Upstream `tests/test_filters/test_logic.py` cases deliberately not ported:
 *
 * - `TestOrFilter::test_dunder_methods` — Python `__or__` / `__and__` / `__invert__` operator
 *   overloads on filter instances cannot be expressed in PHP; the port uses named factory
 *   methods `Filter::all()`, `Filter::any()`, `Filter::invertOf()` instead (reason 3).
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 */
final class OrFilterTest extends TestCase
{
  public function testEmptyOrFilterRejectsWithFalse(): void
  {
    // Upstream: the `for target in self.targets:` loop is a no-op so the
    // function falls through to `return False`. PHP mirrors that — an
    // empty OrFilter is the identity-reject (the dual of empty AndFilter).
    $filter = new OrFilter();

    self::assertFalse($filter($this->event()));
  }

  public function testAllTargetsReturnFalseResultsInFalse(): void
  {
    // No child accepts → the combinator rejects. Verify the loop walks
    // every target before giving up (no short-circuit on the way to the
    // final `false`).
    $filter = new OrFilter(
      $this->filter(static fn(): bool => false),
      $this->filter(static fn(): bool => false),
      $this->filter(static fn(): bool => false),
    );

    self::assertFalse($filter($this->event()));
  }

  public function testFirstTrueShortCircuitsAndReturnsTrue(): void
  {
    // `if result: return result` — first accepting filter wins. The
    // remaining targets must not run; capture that with a flag closure
    // to prove the short-circuit behavior, not just the return value.
    $secondRan = false;
    $filter = new OrFilter(
      $this->filter(static fn(): bool => true),
      $this->filter(static function () use (&$secondRan): bool {
        $secondRan = true;

        return true;
      }),
    );

    self::assertTrue($filter($this->event()));
    self::assertFalse($secondRan, 'OrFilter must short-circuit after the first accept.');
  }

  public function testFirstArrayShortCircuitsAndReturnsThatArray(): void
  {
    // Array return is a truthy accept that ALSO contributes kwargs;
    // OrFilter forwards that array unchanged (no merging across targets,
    // unlike AndFilter — only one branch wins). Later targets are skipped.
    $secondRan = false;
    $filter = new OrFilter(
      $this->filter(static fn(): array => ['match' => 'first']),
      $this->filter(static function () use (&$secondRan): bool {
        $secondRan = true;

        return true;
      }),
    );

    self::assertSame(['match' => 'first'], $filter($this->event()));
    self::assertFalse($secondRan, 'OrFilter must short-circuit after the first accept.');
  }

  public function testEachFilterReceivesIdenticalKwargsWithoutCascade(): void
  {
    // Unlike AndFilter, OrFilter does NOT thread filter N-1's return into
    // filter N's kwargs (upstream `await target(*args, **kwargs)` reuses
    // the original kwargs every iteration). Verify both children see the
    // same input map even when the first rejects.
    $captured = [];
    $vote = static function (object $e, array $kwargs) use (&$captured): bool {
      $captured[] = $kwargs;

      return false;
    };
    $filter = new OrFilter(
      $this->filter($vote),
      $this->filter($vote),
    );

    $filter($this->event(), ['original' => 'value']);

    self::assertSame(
      [['original' => 'value'], ['original' => 'value']],
      $captured,
      'OrFilter must reuse the original kwargs for every child (no cascade).',
    );
  }

  /**
   * @param Closure(object, array<string, mixed>): (array<string, mixed>|bool) $vote
   */
  private function filter(Closure $vote): Filter
  {
    return new class ($vote) extends Filter {
      /** @param Closure(object, array<string, mixed>): (array<string, mixed>|bool) $vote */
      public function __construct(private readonly Closure $vote) {}

      public function __invoke(object $event, array $kwargs = []): array|bool
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
