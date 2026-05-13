<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\MagicFilter\Operation;

use Closure;
use Gruven\PhpBotGram\Utils\MagicFilter\Exception\RejectOperations;
use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilter;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\FunctionOperation;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\ImportantFunctionOperation;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

/**
 * Unit coverage for `FunctionOperation` and its `Important` flavour.
 * The function family is the heaviest by use — it backs `func`, `len`,
 * `lower`, `upper`, `regexp`, `startsWith`, `endsWith`, `contains`, etc.
 */
final class FunctionOperationTest extends TestCase
{
  public function testCallsFunctionWithValueAsLastPositional(): void
  {
    // Upstream order: `function(*args, value, **kwargs)`. With no extra
    // args this collapses to `function(value)`.
    $op = new FunctionOperation(static fn(int $v): int => $v * 2);

    self::assertSame(14, $op->resolve(7, null));
  }

  public function testPrependsExtraArgsBeforeValue(): void
  {
    // Two extra positional args land BEFORE the value: classic
    // `in_op(haystack, value)` order.
    $op = new FunctionOperation(
      static fn(int $threshold, int $value): bool => $value >= $threshold,
      [10],
    );

    self::assertTrue($op->resolve(20, null));
    self::assertFalse($op->resolve(5, null));
  }

  public function testResolvesMagicFilterArgsAgainstInitialValue(): void
  {
    // Args that are themselves MagicFilter chains get resolved against
    // the chain's original subject before being passed in. Lets
    // `func(IsInHaystack, F->ctx->allowed)` work.
    $subject = new stdClass();
    $subject->threshold = 10;

    $right = MagicFilter::root()->threshold;
    $op = new FunctionOperation(
      static fn(int $threshold, int $value): bool => $value >= $threshold,
      [$right],
    );

    self::assertTrue($op->resolve(20, $subject));
    self::assertFalse($op->resolve(5, $subject));
  }

  public function testCatchesExceptionAndConvertsToReject(): void
  {
    // A failing user closure → RejectOperations. The chain stays
    // composable; an unexpected error in a predicate doesn't blow up
    // the dispatcher.
    $op = new FunctionOperation(static fn(): never => throw new RuntimeException('boom'));

    $this->expectException(RejectOperations::class);
    $op->resolve('any', null);
  }

  public function testImportantFunctionOperationFlipsImportantFlag(): void
  {
    // The Important variant opts into the resolver's "always-run"
    // policy via `important()` returning true.
    $op = new ImportantFunctionOperation(static fn(): bool => true);

    self::assertTrue($op->important());
  }

  public function testForwardsKwargsByName(): void
  {
    // Named arguments survive into the call.
    $fn = static fn(int $value, int $offset = 0): int => $value + $offset;
    $op = new FunctionOperation(Closure::fromCallable($fn), [], ['offset' => 5]);

    self::assertSame(15, $op->resolve(10, null));
  }
}
