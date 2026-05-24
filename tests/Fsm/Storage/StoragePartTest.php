<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm\Storage;

use Gruven\PhpBotGram\Fsm\Storage\StoragePart;
use PHPUnit\Framework\TestCase;

/**
 * Upstream `tests/test_fsm/storage/test_key_builder.py` `StoragePart` usage
 * cases deliberately not ported here:
 *
 * - No deliberate skips. All `StoragePart` wire-value cases from the upstream
 *   `DefaultKeyBuilder.build(key, field)` calls are covered by this file and
 *   `DefaultKeyBuilderTest`.
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 *
 * @internal
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
