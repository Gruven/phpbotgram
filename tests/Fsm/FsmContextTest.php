<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm;

use Gruven\PhpBotGram\Fsm\FsmContext;
use Gruven\PhpBotGram\Fsm\State;
use Gruven\PhpBotGram\Fsm\Storage\MemoryStorage;
use Gruven\PhpBotGram\Fsm\Storage\StorageKey;
use PHPUnit\Framework\TestCase;

/**
 * Covers `FsmContext` — the per-context FSM handle.
 *
 * Uses `MemoryStorage` as the concrete backend so tests run without any I/O.
 * Mirrors `aiogram.fsm.context.FSMContext` (`aiogram/fsm/context.py`).
 */
final class FsmContextTest extends TestCase
{
  private MemoryStorage $storage;
  private StorageKey $key;
  private FsmContext $ctx;

  protected function setUp(): void
  {
    $this->storage = new MemoryStorage();
    $this->key = new StorageKey(botId: 1, chatId: 100, userId: 42);
    $this->ctx = new FsmContext($this->storage, $this->key);
  }

  // ------------------------------------------------------------------ //
  // Constructor / structure
  // ------------------------------------------------------------------ //

  /**
   * The constructor exposes `storage` and `key` as public readonly properties.
   */
  public function testConstructorExposesReadonlyProperties(): void
  {
    self::assertSame($this->storage, $this->ctx->storage);
    self::assertSame($this->key, $this->ctx->key);
  }

  // ------------------------------------------------------------------ //
  // setState / getState
  // ------------------------------------------------------------------ //

  /**
   * `setState` + `getState` round-trip with a plain string.
   */
  public function testSetStateGetStateRoundTripWithString(): void
  {
    $this->ctx->setState('Form:step_one');

    self::assertSame('Form:step_one', $this->ctx->getState());
  }

  /**
   * `setState` with a `State` instance stores the qualified state name.
   */
  public function testSetStateWithStateObject(): void
  {
    $state = new State(state: 'idle', groupName: 'Wizard');
    $this->ctx->setState($state);

    self::assertSame('Wizard:idle', $this->ctx->getState());
  }

  /**
   * `setState(null)` clears the state.
   */
  public function testSetStateNullClearsState(): void
  {
    $this->ctx->setState('active');
    $this->ctx->setState(null);

    self::assertNull($this->ctx->getState());
  }

  /**
   * `getState` returns `null` before any state has been set.
   */
  public function testGetStateReturnsNullInitially(): void
  {
    self::assertNull($this->ctx->getState());
  }

  // ------------------------------------------------------------------ //
  // setData / getData
  // ------------------------------------------------------------------ //

  /**
   * `setData` + `getData` round-trip with a small dict.
   */
  public function testSetDataGetDataRoundTrip(): void
  {
    $data = ['name' => 'Alice', 'step' => 3];
    $this->ctx->setData($data);

    self::assertSame($data, $this->ctx->getData());
  }

  /**
   * `getData` returns an empty array before any data has been stored.
   */
  public function testGetDataReturnsEmptyArrayInitially(): void
  {
    self::assertSame([], $this->ctx->getData());
  }

  // ------------------------------------------------------------------ //
  // getValue
  // ------------------------------------------------------------------ //

  /**
   * `getValue` returns the stored value for a present key.
   */
  public function testGetValueReturnsPresentValue(): void
  {
    $this->ctx->setData(['score' => 99]);

    self::assertSame(99, $this->ctx->getValue('score'));
  }

  /**
   * `getValue` returns `null` (default) for a missing key.
   */
  public function testGetValueReturnsNullDefaultForMissingKey(): void
  {
    self::assertNull($this->ctx->getValue('missing'));
  }

  /**
   * `getValue` returns the provided `$default` for a missing key.
   */
  public function testGetValueReturnsCustomDefaultForMissingKey(): void
  {
    self::assertSame('fallback', $this->ctx->getValue('missing', 'fallback'));
  }

  // ------------------------------------------------------------------ //
  // updateData
  // ------------------------------------------------------------------ //

  /**
   * `updateData` with only named kwargs merges them into the existing data.
   */
  public function testUpdateDataWithKwargsOnly(): void
  {
    $this->ctx->setData(['a' => 1]);
    $result = $this->ctx->updateData(b: 2);

    self::assertSame(['a' => 1, 'b' => 2], $result);
    self::assertSame(['a' => 1, 'b' => 2], $this->ctx->getData());
  }

  /**
   * `updateData(['a' => 1], b: 2)` merges both sources into the stored data.
   *
   * Key order: kwargs go in first (`array_merge($kwargs, $data)`), then $data
   * is overlaid on top; result order is kwargs-first, data-second for
   * non-overlapping keys.
   */
  public function testUpdateDataMergesArrayAndKwargs(): void
  {
    $result = $this->ctx->updateData(['a' => 1], b: 2);

    // Both keys must be present; order may differ due to merge sequence.
    self::assertSame(1, $result['a']);
    self::assertSame(2, $result['b']);
    self::assertCount(2, $result);
  }

  /**
   * Upstream overlap semantics: `$data` wins over `$kwargs` when both provide
   * the same key.
   *
   * Upstream Python: `kwargs.update(data or {})` → data overwrites kwargs.
   * PHP equivalent: `array_merge($kwargs, $data)` → same semantics.
   */
  public function testUpdateDataArrayWinsOverKwargsOnOverlap(): void
  {
    // Pass a: 99 via kwargs AND a: 1 via $data.  $data must win → a: 1.
    $result = $this->ctx->updateData(['a' => 1], a: 99);

    self::assertSame(['a' => 1], $result, '$data must win over kwargs on key collision');
  }

  /**
   * `updateData` merges into the existing data record, not just the supplied args.
   */
  public function testUpdateDataMergesIntoExistingRecord(): void
  {
    $this->ctx->setData(['existing' => 'keep']);
    $result = $this->ctx->updateData(['new' => 'add']);

    self::assertSame(['existing' => 'keep', 'new' => 'add'], $result);
  }

  // ------------------------------------------------------------------ //
  // clear
  // ------------------------------------------------------------------ //

  /**
   * `clear()` resets state to `null`.
   */
  public function testClearResetsState(): void
  {
    $this->ctx->setState('running');
    $this->ctx->clear();

    self::assertNull($this->ctx->getState());
  }

  /**
   * `clear()` resets data to an empty array.
   */
  public function testClearResetsData(): void
  {
    $this->ctx->setData(['k' => 'v']);
    $this->ctx->clear();

    self::assertSame([], $this->ctx->getData());
  }

  /**
   * `clear()` resets both state and data in a single call.
   */
  public function testClearResetsBothStateAndData(): void
  {
    $this->ctx->setState('Form:step_one');
    $this->ctx->setData(['answer' => 42]);

    $this->ctx->clear();

    self::assertNull($this->ctx->getState());
    self::assertSame([], $this->ctx->getData());
  }

  // ------------------------------------------------------------------ //
  // Key isolation
  // ------------------------------------------------------------------ //

  /**
   * Two `FsmContext` instances on different keys do not interfere.
   */
  public function testDistinctContextsAreIsolated(): void
  {
    $keyB = new StorageKey(botId: 1, chatId: 100, userId: 99);
    $ctxB = new FsmContext($this->storage, $keyB);

    $this->ctx->setState('state_a');
    $ctxB->setState('state_b');

    self::assertSame('state_a', $this->ctx->getState());
    self::assertSame('state_b', $ctxB->getState());
  }
}
