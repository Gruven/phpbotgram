<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\MagicFilter\Operation;

use ArrayIterator;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\AsFilterResultOperation;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for `AsFilterResultOperation` — the terminal step that
 * wraps the chain's final value as `[name => value]` (accept) or `null`
 * (reject).
 */
final class AsFilterResultOperationTest extends TestCase
{
  public function testWrapsTruthyValueAsKwargArray(): void
  {
    // Truthy scalar → kwarg map.
    $op = new AsFilterResultOperation('value');

    self::assertSame(['value' => 'hello'], $op->resolve('hello', null));
  }

  public function testFalsyButNonNullValueStillAccepts(): void
  {
    // `false`, `0`, `''` are valid payload values — they should be
    // packaged, not rejected. This is the upstream semantic that
    // distinguishes `as_` from a naive "truthy or reject" wrapper.
    $op = new AsFilterResultOperation('flag');

    self::assertSame(['flag' => false], $op->resolve(false, null));
    self::assertSame(['flag' => 0], $op->resolve(0, null));
    self::assertSame(['flag' => ''], $op->resolve('', null));
  }

  public function testNullValueRejects(): void
  {
    // `null` is the only "rejected" scalar — returns null (the rejection
    // signal that the MagicFilterAsFilter bridge collapses to a hard
    // false).
    $op = new AsFilterResultOperation('value');

    self::assertNull($op->resolve(null, null));
  }

  public function testEmptyArrayRejects(): void
  {
    // Empty iterable → rejection. Matches upstream's
    // `isinstance(value, Iterable) and not value` clause.
    $op = new AsFilterResultOperation('matches');

    self::assertNull($op->resolve([], null));
  }

  public function testNonEmptyArrayWraps(): void
  {
    $op = new AsFilterResultOperation('matches');

    self::assertSame(
      ['matches' => ['a', 'b']],
      $op->resolve(['a', 'b'], null),
    );
  }

  public function testIterableEmptyRejectsAfterMaterialisation(): void
  {
    // Iterator path: we materialise to detect emptiness. The empty
    // iterator collapses to a rejection.
    $op = new AsFilterResultOperation('m');
    $empty = new ArrayIterator([]);

    self::assertNull($op->resolve($empty, null));
  }

  public function testIterableNonEmptyMaterialisesAndWraps(): void
  {
    // Non-empty iterators get materialised into an array under the kwarg.
    $op = new AsFilterResultOperation('m');
    $iter = new ArrayIterator(['x', 'y']);

    self::assertSame(['m' => ['x', 'y']], $op->resolve($iter, null));
  }
}
