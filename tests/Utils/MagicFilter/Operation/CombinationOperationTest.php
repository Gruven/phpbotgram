<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\MagicFilter\Operation;

use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilter;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\CombinationOperation;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\ImportantCombinationOperation;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\RCombinationOperation;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Unit coverage for the combination family — `CombinationOperation` and
 * its `Important` + `R` (reverse) flavours. These power AND/OR/XOR and
 * the upstream r-variants for `'pong' + F.text`-style call sites.
 */
final class CombinationOperationTest extends TestCase
{
  public function testStandardCombinatorWithLiteralRight(): void
  {
    // value AND literal using the Python "value-preserving and" — when
    // left is truthy the combinator returns the right operand.
    $op = new CombinationOperation(42, static fn(mixed $a, mixed $b): mixed => $a ? $b : $a);

    self::assertSame(42, $op->resolve(true, null));
    self::assertFalse($op->resolve(false, null));
  }

  public function testResolvesMagicFilterRightOperand(): void
  {
    // Nested chain on the right resolves against the chain's original
    // subject — matches `F->a & F->b` upstream.
    $subject = new stdClass();
    $subject->b = 42;

    $right = MagicFilter::root()->b;
    $op = new CombinationOperation($right, static fn(mixed $a, mixed $b): mixed => $a ? $b : $a);

    self::assertSame(42, $op->resolve(true, $subject));
  }

  public function testImportantVariantSetsImportantFlag(): void
  {
    // Subclass-only behaviour: `important()` returns true, telling the
    // resolver to run this op even after an earlier reject.
    $op = new ImportantCombinationOperation('x', static fn(mixed $a, mixed $b): mixed => $a ?: $b);

    self::assertTrue($op->important());
  }

  public function testRCombinationFlipsOperandOrder(): void
  {
    // Reverse: literal on the LEFT, running value on the RIGHT. Matches
    // upstream's `__rxxx__` family.
    $op = new RCombinationOperation('prefix-', static fn(string $a, string $b): string => $a . $b);

    self::assertSame('prefix-data', $op->resolve('data', null));
  }
}
