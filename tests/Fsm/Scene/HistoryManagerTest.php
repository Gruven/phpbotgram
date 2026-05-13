<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm\Scene;

use Gruven\PhpBotGram\Fsm\FsmContext;
use Gruven\PhpBotGram\Fsm\Scene\HistoryManager;
use Gruven\PhpBotGram\Fsm\Storage\MemoryStorage;
use Gruven\PhpBotGram\Fsm\Storage\MemoryStorageRecord;
use Gruven\PhpBotGram\Fsm\Storage\StorageKey;
use PHPUnit\Framework\TestCase;

/**
 * Upstream `tests/test_fsm/test_scene.py::TestHistoryManager` cases
 * deliberately not ported:
 *
 * - `TestHistoryManager::test_history_manager_push` and related async cases —
 *   upstream uses `MemoryStorage` async `await` calls; PHP port is synchronous
 *   but all the same contracts are verified in this file.
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 */
final class HistoryManagerTest extends TestCase
{
  // ------------------------------------------------------------------ //
  // Fixtures
  // ------------------------------------------------------------------ //

  private function makeStorage(): MemoryStorage
  {
    return new MemoryStorage();
  }

  private function makeKey(string $destiny = 'default'): StorageKey
  {
    return new StorageKey(botId: 1, chatId: 100, userId: 42, destiny: $destiny);
  }

  private function makeContext(?MemoryStorage $storage = null, ?StorageKey $key = null): FsmContext
  {
    return new FsmContext(
      storage: $storage ?? $this->makeStorage(),
      key: $key ?? $this->makeKey(),
    );
  }

  // ------------------------------------------------------------------ //
  // push()
  // ------------------------------------------------------------------ //

  /**
   * `push` appends an entry to the history slot under a separate destiny.
   */
  public function testPushAppendsToHistorySlot(): void
  {
    $storage = $this->makeStorage();
    $key = $this->makeKey();
    $ctx = new FsmContext($storage, $key);

    $manager = new HistoryManager($ctx);
    $manager->push('step1', ['foo' => 'bar']);

    // Read indirectly via the manager API (avoids type-unsafe raw storage access).
    $all = $manager->all();

    self::assertCount(1, $all);
    self::assertSame('step1', $all[0]->state);
    self::assertSame(['foo' => 'bar'], $all[0]->data);

    // Also verify isolation by checking the history is in the separate destiny slot.
    $historyKey = $key->withDestiny('scenes_history');
    $rawSlot = $storage->getData($historyKey);
    self::assertArrayHasKey('history', $rawSlot);
  }

  /**
   * `push` does NOT bleed into the main FSM storage slot.
   */
  public function testPushDoesNotBleedIntoMainSlot(): void
  {
    $storage = $this->makeStorage();
    $key = $this->makeKey();
    $ctx = new FsmContext($storage, $key);

    $ctx->setState('main_state');
    $ctx->setData(['main' => true]);

    $manager = new HistoryManager($ctx);
    $manager->push('step1', ['foo' => 'bar']);

    // Main slot must be untouched.
    self::assertSame('main_state', $ctx->getState());
    self::assertSame(['main' => true], $ctx->getData());
  }

  /**
   * `push` evicts the oldest entry when the list exceeds `$size`.
   *
   * With `$size=2` and three pushes, only the last two entries remain.
   */
  public function testPushEvictsOldestWhenOverSize(): void
  {
    $ctx = $this->makeContext();
    $manager = new HistoryManager($ctx, 'scenes_history', 2);

    $manager->push('step1', ['x' => 1]);
    $manager->push('step2', ['x' => 2]);
    $manager->push('step3', ['x' => 3]);

    $all = $manager->all();

    self::assertCount(2, $all);
    self::assertSame('step2', $all[0]->state);
    self::assertSame('step3', $all[1]->state);
  }

  // ------------------------------------------------------------------ //
  // pop()
  // ------------------------------------------------------------------ //

  /**
   * `pop` returns the last entry and removes it from the stack.
   */
  public function testPopReturnsLastEntryAndRemovesIt(): void
  {
    $ctx = $this->makeContext();
    $manager = new HistoryManager($ctx);

    $manager->push('step1', ['a' => 1]);
    $manager->push('step2', ['a' => 2]);

    $popped = $manager->pop();

    self::assertInstanceOf(MemoryStorageRecord::class, $popped);
    self::assertSame('step2', $popped->state);
    self::assertSame(['a' => 2], $popped->data);

    // Only step1 remains.
    self::assertCount(1, $manager->all());
    self::assertSame('step1', $manager->all()[0]->state);
  }

  /**
   * `pop` on an empty history returns `null`.
   */
  public function testPopOnEmptyHistoryReturnsNull(): void
  {
    $ctx = $this->makeContext();
    $manager = new HistoryManager($ctx);

    self::assertNull($manager->pop());
  }

  // ------------------------------------------------------------------ //
  // get()
  // ------------------------------------------------------------------ //

  /**
   * `get` peeks the last entry without removing it.
   */
  public function testGetPeeksLastEntryWithoutRemoving(): void
  {
    $ctx = $this->makeContext();
    $manager = new HistoryManager($ctx);

    $manager->push('step1', ['v' => 1]);
    $manager->push('step2', ['v' => 2]);

    $peeked = $manager->get();

    self::assertInstanceOf(MemoryStorageRecord::class, $peeked);
    self::assertSame('step2', $peeked->state);

    // Stack size unchanged.
    self::assertCount(2, $manager->all());
  }

  /**
   * `get` on an empty history returns `null`.
   */
  public function testGetOnEmptyHistoryReturnsNull(): void
  {
    $ctx = $this->makeContext();
    $manager = new HistoryManager($ctx);

    self::assertNull($manager->get());
  }

  // ------------------------------------------------------------------ //
  // all()
  // ------------------------------------------------------------------ //

  /**
   * `all` returns every entry as a list of `MemoryStorageRecord` objects.
   */
  public function testAllReturnsEveryEntry(): void
  {
    $ctx = $this->makeContext();
    $manager = new HistoryManager($ctx);

    $manager->push('a', ['i' => 1]);
    $manager->push('b', ['i' => 2]);
    $manager->push('c', ['i' => 3]);

    $all = $manager->all();

    self::assertCount(3, $all);
    self::assertSame('a', $all[0]->state);
    self::assertSame('b', $all[1]->state);
    self::assertSame('c', $all[2]->state);
  }

  /**
   * `all` on an empty history returns an empty list.
   */
  public function testAllOnEmptyHistoryReturnsEmptyList(): void
  {
    $ctx = $this->makeContext();
    $manager = new HistoryManager($ctx);

    self::assertSame([], $manager->all());
  }

  /**
   * `push` merges into existing history-slot data, preserving sibling keys.
   *
   * Regression: `setData(['history' => ...])` would wipe ALL fields under
   * `data` (analogous to Mongo `$set: {data: ...}`), losing any sibling key
   * that was already stored in the same destiny slot. `updateData` performs
   * a partial-key merge instead, leaving unrelated keys intact.
   */
  public function testPushPreservesSiblingKeysInHistorySlot(): void
  {
    $storage = $this->makeStorage();
    $key = $this->makeKey();
    $ctx = new FsmContext($storage, $key);

    // Pre-seed the history destiny slot with a sibling key alongside 'history'.
    $historyKey = $key->withDestiny('scenes_history');
    $storage->setData($historyKey, ['history' => [], 'sibling' => 'preserved']);

    $manager = new HistoryManager($ctx);
    $manager->push('step1', ['foo' => 'bar']);

    // The sibling key must still be present after push.
    $rawSlot = $storage->getData($historyKey);
    self::assertArrayHasKey('sibling', $rawSlot, 'sibling key must survive push');
    self::assertSame('preserved', $rawSlot['sibling']);

    // And history must contain the new entry.
    self::assertArrayHasKey('history', $rawSlot);
    self::assertIsArray($rawSlot['history']);
    self::assertCount(1, $rawSlot['history']);
  }

  // ------------------------------------------------------------------ //
  // clear()
  // ------------------------------------------------------------------ //

  /**
   * `clear` empties the history stack entirely.
   */
  public function testClearEmptiesHistory(): void
  {
    $ctx = $this->makeContext();
    $manager = new HistoryManager($ctx);

    $manager->push('step1', []);
    $manager->push('step2', []);

    $manager->clear();

    self::assertSame([], $manager->all());
  }

  // ------------------------------------------------------------------ //
  // snapshot()
  // ------------------------------------------------------------------ //

  /**
   * `snapshot` captures the current main FSM state and data onto the stack.
   */
  public function testSnapshotCapturesMainState(): void
  {
    $ctx = $this->makeContext();
    $ctx->setState('active_scene');
    $ctx->setData(['step' => 3]);

    $manager = new HistoryManager($ctx);
    $manager->snapshot();

    $top = $manager->get();

    self::assertNotNull($top);
    self::assertSame('active_scene', $top->state);
    self::assertSame(['step' => 3], $top->data);
  }

  // ------------------------------------------------------------------ //
  // rollback()
  // ------------------------------------------------------------------ //

  /**
   * `rollback` pops the last entry and restores it to the main FSM context.
   */
  public function testRollbackRestoresMainFsmState(): void
  {
    $ctx = $this->makeContext();
    $manager = new HistoryManager($ctx);

    $manager->push('scene_a', ['k' => 'v']);

    $returned = $manager->rollback();

    self::assertSame('scene_a', $returned);
    self::assertSame('scene_a', $ctx->getState());
    self::assertSame(['k' => 'v'], $ctx->getData());
  }

  /**
   * `rollback` on an empty history clears the main FSM state to `null`.
   */
  public function testRollbackOnEmptyHistoryClearsMainState(): void
  {
    $ctx = $this->makeContext();
    $ctx->setState('some_state');
    $ctx->setData(['x' => 1]);

    $manager = new HistoryManager($ctx);

    $returned = $manager->rollback();

    self::assertNull($returned);
    self::assertNull($ctx->getState());
    self::assertSame([], $ctx->getData());
  }
}
