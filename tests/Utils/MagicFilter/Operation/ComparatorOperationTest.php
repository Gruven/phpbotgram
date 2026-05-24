<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\MagicFilter\Operation;

use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilter;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\ComparatorOperation;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Unit coverage for `ComparatorOperation` — the binary comparison step
 * shared by all the `equals`/`gt`/`lt`/… chain extensions.
 *
 * @internal
 */
final class ComparatorOperationTest extends TestCase
{
  public function testRunsTheStoredComparator(): void
  {
    // Custom comparator: greater-than. Verifies the operation passes
    // (left=value, right=stored-right) into the closure in the right
    // order.
    $op = new ComparatorOperation(5, static fn(mixed $a, mixed $b): bool => $a > $b);

    self::assertTrue($op->resolve(10, null));
    self::assertFalse($op->resolve(3, null));
  }

  public function testResolvesNestedMagicFilterOnRightOperand(): void
  {
    // Right operand can itself be a MagicFilter (e.g. `F->id == F->other`).
    // The operation must resolve the right chain against the chain's
    // original subject, not the running value.
    $subject = new stdClass();
    $subject->a = 7;
    $subject->b = 7;

    $right = MagicFilter::root()->b;
    $op = new ComparatorOperation($right, static fn(mixed $a, mixed $b): bool => $a == $b);

    // Running value mirrors what `F->a` would produce — fetch and pass.
    self::assertTrue($op->resolve(7, $subject));
    self::assertFalse($op->resolve(8, $subject));
  }
}
