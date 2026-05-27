<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\MagicFilter\Operation;

use Gruven\PhpBotGram\Utils\MagicFilter\Exception\RejectOperations;
use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilter;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\ExtractOperation;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\SelectorOperation;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for `SelectorOperation` and `ExtractOperation` — the
 * sub-filter operations.
 *
 * @internal
 */
final class SelectorAndExtractOperationTest extends TestCase
{
  public function testSelectorPassesValueThroughOnInnerAccept(): void
  {
    // Selector returns the input value verbatim when the inner accepts.
    $inner = MagicFilter::root()->gt(5);
    $op = new SelectorOperation($inner);

    self::assertSame(10, $op->resolve(10, null));
  }

  public function testSelectorRejectsOnInnerRejection(): void
  {
    // Inner reject → SelectorOperation raises RejectOperations.
    $inner = MagicFilter::root()->gt(5);
    $op = new SelectorOperation($inner);

    $this->expectException(RejectOperations::class);
    $op->resolve(3, null);
  }

  public function testExtractKeepsItemsForWhichInnerAccepts(): void
  {
    // `extract` keeps the matched elements as a list — like Python's
    // `[x for x in xs if predicate(x)]`.
    $inner = MagicFilter::root()->gt(5);
    $op = new ExtractOperation($inner);

    self::assertSame([10, 20], $op->resolve([1, 10, 3, 20, 5], null));
  }

  public function testExtractReturnsNullForNonIterableSubject(): void
  {
    // Non-iterable subject → null (matches upstream's defensive return).
    $inner = MagicFilter::root()->gt(5);
    $op = new ExtractOperation($inner);

    self::assertNull($op->resolve('not iterable', null));
  }
}
