<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\MagicFilter\Exception;

use Gruven\PhpBotGram\Utils\MagicFilter\Exception\MagicFilterException;
use Gruven\PhpBotGram\Utils\MagicFilter\Exception\ParamsConflict;
use Gruven\PhpBotGram\Utils\MagicFilter\Exception\RejectOperations;
use Gruven\PhpBotGram\Utils\MagicFilter\Exception\SwitchMode;
use Gruven\PhpBotGram\Utils\MagicFilter\Exception\SwitchModeToAll;
use Gruven\PhpBotGram\Utils\MagicFilter\Exception\SwitchModeToAny;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Sanity check on the exception hierarchy: the magic-filter exceptions
 * must remain a closed family rooted at `MagicFilterException` so
 * callers can catch the whole family with one clause.
 */
final class ExceptionsTest extends TestCase
{
  public function testHierarchyRootsAtMagicFilterException(): void
  {
    // The base is a `RuntimeException` so generic catch blocks still
    // trap it, but every specific exception narrows down through it.
    self::assertTrue(is_subclass_of(SwitchMode::class, MagicFilterException::class));
    self::assertTrue(is_subclass_of(SwitchModeToAll::class, SwitchMode::class));
    self::assertTrue(is_subclass_of(SwitchModeToAny::class, SwitchMode::class));
    self::assertTrue(is_subclass_of(RejectOperations::class, MagicFilterException::class));
    self::assertTrue(is_subclass_of(ParamsConflict::class, MagicFilterException::class));

    self::assertTrue(is_subclass_of(MagicFilterException::class, RuntimeException::class));
  }

  public function testSwitchModeToAllCarriesKey(): void
  {
    // The carried `key` is the marker that triggered the fan-out —
    // typically `MagicFilter::WILDCARD_ALL`. Stored for parity with
    // upstream's `slice` value, not strictly required by the resolver.
    $exc = new SwitchModeToAll('marker');

    self::assertSame('marker', $exc->key);
  }

  public function testRejectOperationsWrapsPreviousThrowable(): void
  {
    // The third `$previous` chain mirrors upstream's `raise … from e`.
    $cause = new RuntimeException('cause');
    $exc = new RejectOperations($cause);

    self::assertSame($cause, $exc->getPrevious());
    self::assertSame('cause', $exc->getMessage());
  }

  public function testRejectOperationsWorksWithoutPrevious(): void
  {
    // Selector-style rejections don't carry a cause.
    $exc = new RejectOperations();

    self::assertNull($exc->getPrevious());
    self::assertSame('', $exc->getMessage());
  }
}
