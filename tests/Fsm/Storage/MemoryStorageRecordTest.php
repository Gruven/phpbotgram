<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm\Storage;

use Gruven\PhpBotGram\Fsm\Storage\MemoryStorageRecord;
use PHPUnit\Framework\TestCase;

/**
 * Covers `MemoryStorageRecord`.
 *
 * Mirrors the dataclass contract from `aiogram/fsm/storage/memory.py:20-22`.
 */
final class MemoryStorageRecordTest extends TestCase
{
  /**
   * Default (arg-less) construction yields an empty data array and null state.
   */
  public function testDefaultConstructionYieldsEmptyDataAndNullState(): void
  {
    $record = new MemoryStorageRecord();

    self::assertSame([], $record->data);
    self::assertNull($record->state);
  }

  /**
   * Constructor with explicit data and state stores them as given.
   */
  public function testExplicitDataAndStateAreStoredAsGiven(): void
  {
    $record = new MemoryStorageRecord(
      data: ['foo' => 'bar', 'count' => 7],
      state: 'MyGroup:active',
    );

    self::assertSame(['foo' => 'bar', 'count' => 7], $record->data);
    self::assertSame('MyGroup:active', $record->state);
  }

  /**
   * Properties are publicly mutable — assigning `$record->data` and
   * `$record->state` updates the values in place.
   */
  public function testPropertiesAreMutable(): void
  {
    $record = new MemoryStorageRecord();

    $record->data = ['key' => 'value'];
    $record->state = 'new_state';

    self::assertSame(['key' => 'value'], $record->data);
    self::assertSame('new_state', $record->state);
  }

  /**
   * Assigning `null` to `state` explicitly resets it.
   */
  public function testSettingStateToNullResetsIt(): void
  {
    $record = new MemoryStorageRecord(state: 'active');

    self::assertSame('active', $record->state);

    $record->state = null;

    self::assertNull($record->state);
  }
}
