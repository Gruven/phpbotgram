<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\MagicFilter\Operation;

use Closure;
use Gruven\PhpBotGram\Utils\MagicFilter\Exception\RejectOperations;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\CastOperation;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit coverage for `CastOperation` — the simplest unary transform step.
 *
 * @internal
 */
final class CastOperationTest extends TestCase
{
  public function testAppliesCastFunction(): void
  {
    $op = new CastOperation(Closure::fromCallable('intval'));

    self::assertSame(42, $op->resolve('42', null));
    self::assertSame(0, $op->resolve('not-a-number', null));
  }

  public function testWrapsExceptionAsReject(): void
  {
    // Any exception from the cast → RejectOperations. Matches upstream's
    // `except Exception: raise RejectOperations(e) from e`.
    $op = new CastOperation(static fn(): int => throw new RuntimeException('boom'));

    $this->expectException(RejectOperations::class);
    $op->resolve('any', null);
  }
}
