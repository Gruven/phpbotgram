<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm\Storage;

use Gruven\PhpBotGram\Fsm\Storage\StoragePart;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that `StoragePart` wire values match the upstream Python literals
 * `"data"`, `"state"`, `"lock"` used in `aiogram/fsm/storage/base.py:31,78`.
 *
 * These string values appear verbatim in stored keys and in wire comparisons;
 * any divergence would silently corrupt stored FSM records.
 */
final class StoragePartTest extends TestCase
{
  public function testDataValueMatchesWireForm(): void
  {
    self::assertSame('data', StoragePart::Data->value);
  }

  public function testStateValueMatchesWireForm(): void
  {
    self::assertSame('state', StoragePart::State->value);
  }

  public function testLockValueMatchesWireForm(): void
  {
    self::assertSame('lock', StoragePart::Lock->value);
  }

  public function testFromStringRoundTripForData(): void
  {
    self::assertSame(StoragePart::Data, StoragePart::from('data'));
  }

  public function testFromStringRoundTripForState(): void
  {
    self::assertSame(StoragePart::State, StoragePart::from('state'));
  }

  public function testFromStringRoundTripForLock(): void
  {
    self::assertSame(StoragePart::Lock, StoragePart::from('lock'));
  }
}
