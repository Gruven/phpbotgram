<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm\Storage;

use function Amp\async;

use Amp\Future;
use Gruven\PhpBotGram\Fsm\Storage\BaseEventIsolation;
use Gruven\PhpBotGram\Fsm\Storage\DisabledEventIsolation;
use Gruven\PhpBotGram\Fsm\Storage\Lock;
use Gruven\PhpBotGram\Fsm\Storage\SimpleEventIsolation;
use Gruven\PhpBotGram\Fsm\Storage\StorageKey;
use Gruven\PhpBotGram\Tests\Support\RunAsyncTrait;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Revolt\EventLoop;

/**
 * Covers `BaseEventIsolation`, `Lock`, `DisabledEventIsolation`, and
 * `SimpleEventIsolation`.
 *
 * Ported from `aiogram.fsm.storage.base.BaseEventIsolation`
 * (`aiogram/fsm/storage/base.py:200-208`) and
 * `aiogram.fsm.storage.memory.DisabledEventIsolation` /
 * `aiogram.fsm.storage.memory.SimpleEventIsolation`
 * (`aiogram/fsm/storage/memory.py:72-97`).
 */
final class EventIsolationTest extends TestCase
{
  use RunAsyncTrait;

  private StorageKey $key;

  protected function setUp(): void
  {
    $this->key = new StorageKey(botId: 1, chatId: 100, userId: 42);
  }

  // ------------------------------------------------------------------ //
  // Structural / type-level checks
  // ------------------------------------------------------------------ //

  /**
   * `BaseEventIsolation` must be an abstract class.
   */
  public function testBaseEventIsolationIsAbstract(): void
  {
    $rc = new ReflectionClass(BaseEventIsolation::class);

    self::assertTrue($rc->isAbstract());
  }

  /**
   * `DisabledEventIsolation` must extend `BaseEventIsolation`.
   */
  public function testDisabledEventIsolationExtendsBase(): void
  {
    self::assertInstanceOf(BaseEventIsolation::class, new DisabledEventIsolation());
  }

  /**
   * `SimpleEventIsolation` must extend `BaseEventIsolation`.
   */
  public function testSimpleEventIsolationExtendsBase(): void
  {
    self::assertInstanceOf(BaseEventIsolation::class, new SimpleEventIsolation());
  }

  // ------------------------------------------------------------------ //
  // Lock — double-release guard
  // ------------------------------------------------------------------ //

  /**
   * `DisabledEventIsolation::lock` returns a `Lock`.
   */
  public function testDisabledLockReturnedIsLockInstance(): void
  {
    $isolation = new DisabledEventIsolation();
    $lock = $isolation->lock($this->key);

    self::assertInstanceOf(Lock::class, $lock);
  }

  /**
   * `Lock::release()` is idempotent — multiple calls are no-ops (no
   * exception, no side-effect after the first).
   */
  public function testDisabledLockReleaseIsIdempotent(): void
  {
    $isolation = new DisabledEventIsolation();
    $lock = $isolation->lock($this->key);

    // First release — must not throw.
    $lock->release();

    // Second release — must also not throw.
    $lock->release();

    // Third release for good measure.
    $lock->release();

    // If we reach here, the idempotency contract holds.
    self::addToAssertionCount(1);
  }

  // ------------------------------------------------------------------ //
  // DisabledEventIsolation
  // ------------------------------------------------------------------ //

  /**
   * `DisabledEventIsolation::close()` is callable and does not throw.
   */
  public function testDisabledEventIsolationCloseIsNoOp(): void
  {
    $isolation = new DisabledEventIsolation();
    $isolation->close();

    // No exception means pass.
    self::addToAssertionCount(1);
  }

  /**
   * Disabled isolation does not block concurrent locks for the same key —
   * two `lock()` calls acquire immediately without waiting.
   */
  public function testDisabledIsolationDoesNotBlockConcurrentSameKey(): void
  {
    $isolation = new DisabledEventIsolation();

    $acquired = $this->runAsync(static function () use ($isolation): array {
      $key = new StorageKey(botId: 1, chatId: 100, userId: 42);

      $order = [];

      // Both async tasks acquire immediately; no mutual exclusion.
      $f1 = async(static function () use ($isolation, $key, &$order): void {
        $lock = $isolation->lock($key);
        $order[] = 'task1_acquired';
        $lock->release();
        $order[] = 'task1_released';
      });

      $f2 = async(static function () use ($isolation, $key, &$order): void {
        $lock = $isolation->lock($key);
        $order[] = 'task2_acquired';
        $lock->release();
        $order[] = 'task2_released';
      });

      Future\awaitAll([$f1, $f2]);

      return $order;
    });

    self::assertCount(4, $acquired);
  }

  // ------------------------------------------------------------------ //
  // SimpleEventIsolation — mutual-exclusion contract
  // ------------------------------------------------------------------ //

  /**
   * `SimpleEventIsolation::lock` returns a `Lock`.
   */
  public function testSimpleLockReturnedIsLockInstance(): void
  {
    $isolation = new SimpleEventIsolation();

    $lock = $this->runAsync(static function () use ($isolation): Lock {
      $key = new StorageKey(botId: 1, chatId: 100, userId: 42);
      $lock = $isolation->lock($key);
      $lock->release();

      return $lock;
    });

    self::assertInstanceOf(Lock::class, $lock);
  }

  /**
   * `SimpleEventIsolation` serialises concurrent accesses for the SAME key.
   *
   * Task 1 holds the lock and sets a flag. Task 2 blocks until Task 1
   * releases. When Task 2 acquires, the flag must already be `true`.
   */
  public function testSimpleIsolationSerializesConcurrentSameKey(): void
  {
    $isolation = new SimpleEventIsolation();

    // Capture the result via a reference so we avoid an ambiguous
    // mixed return from $future->await() inside runAsync.
    $flagSeen = false;

    $this->runAsync(static function () use ($isolation, &$flagSeen): void {
      $key = new StorageKey(botId: 1, chatId: 100, userId: 42);
      $sharedFlag = false;

      // Task 1: acquire, set flag, hold briefly, then release.
      $f1 = async(static function () use ($isolation, $key, &$sharedFlag): void {
        $lock = $isolation->lock($key);

        try {
          // Yield to the event loop so task 2 can attempt to acquire.
          // Because the lock is held, task 2 will suspend here.
          EventLoop::defer(static function () {});
          $sharedFlag = true;
        } finally {
          $lock->release();
        }
      });

      // Task 2: acquire (blocks until task 1 releases), then capture flag.
      $f2 = async(static function () use ($isolation, $key, &$sharedFlag, &$flagSeen): void {
        $lock = $isolation->lock($key);

        try {
          $flagSeen = $sharedFlag;
        } finally {
          $lock->release();
        }
      });

      // Wait for task 1 to complete first so that task 2 can proceed.
      $f1->await();
      $f2->await();
    });

    self::assertTrue($flagSeen, 'Task 2 must see the flag set by Task 1 — the lock serialized them.');
  }

  /**
   * Distinct `StorageKey` instances do NOT block each other — locks on
   * different keys are independent.
   */
  public function testSimpleIsolationDistinctKeysDoNotBlock(): void
  {
    $isolation = new SimpleEventIsolation();

    $elapsed = $this->runAsync(static function () use ($isolation): float {
      $key1 = new StorageKey(botId: 1, chatId: 100, userId: 1);
      $key2 = new StorageKey(botId: 1, chatId: 100, userId: 2);

      $order = [];

      $start = microtime(true);

      $f1 = async(static function () use ($isolation, $key1, &$order): void {
        $lock = $isolation->lock($key1);

        try {
          $order[] = 'key1_acquired';
        } finally {
          $lock->release();
          $order[] = 'key1_released';
        }
      });

      $f2 = async(static function () use ($isolation, $key2, &$order): void {
        $lock = $isolation->lock($key2);

        try {
          $order[] = 'key2_acquired';
        } finally {
          $lock->release();
          $order[] = 'key2_released';
        }
      });

      Future\awaitAll([$f1, $f2]);

      return microtime(true) - $start;
    });

    // Both tasks should have completed quickly; mutual exclusion across
    // different keys would serialize them artificially.
    self::assertLessThan(1.0, $elapsed, 'Distinct keys must not block each other.');
  }

  /**
   * After `release()`, another `lock()` for the same key can acquire
   * immediately (the mutex is free again).
   */
  public function testSimpleIsolationLockReleasedAllowsReacquire(): void
  {
    $isolation = new SimpleEventIsolation();

    $this->runAsync(static function () use ($isolation): void {
      $key = new StorageKey(botId: 1, chatId: 100, userId: 42);

      // First acquire + release.
      $lock1 = $isolation->lock($key);
      $lock1->release();

      // Second acquire — must not block (lock1 was released).
      $lock2 = $isolation->lock($key);
      $lock2->release();
    });

    // Reaching here means no deadlock.
    self::addToAssertionCount(1);
  }

  /**
   * `SimpleEventIsolation::close()` is callable and does not throw.
   */
  public function testSimpleEventIsolationCloseIsCallable(): void
  {
    $isolation = new SimpleEventIsolation();

    $this->runAsync(static function () use ($isolation): void {
      $key = new StorageKey(botId: 1, chatId: 100, userId: 42);

      // Acquire and release once before close.
      $lock = $isolation->lock($key);
      $lock->release();
    });

    // Must not throw.
    $isolation->close();

    // After close, new locks should still work (fresh mutex).
    $this->runAsync(static function () use ($isolation): void {
      $key = new StorageKey(botId: 1, chatId: 100, userId: 42);
      $lock = $isolation->lock($key);
      $lock->release();
    });

    self::addToAssertionCount(1);
  }

  /**
   * Key derivation: two `StorageKey` instances with different fields produce
   * distinct mutex keys and do not block each other.
   */
  public function testSimpleIsolationFullKeyDerivationUsesAllFields(): void
  {
    $isolation = new SimpleEventIsolation();

    $this->runAsync(static function () use ($isolation): void {
      // Same botId/chatId/userId but different destiny.
      $keyA = new StorageKey(botId: 1, chatId: 100, userId: 42, destiny: 'wizard');
      $keyB = new StorageKey(botId: 1, chatId: 100, userId: 42, destiny: 'default');

      // Acquire A, then immediately acquire B — must not deadlock because
      // they map to different mutex keys.
      $lockA = $isolation->lock($keyA);
      $lockB = $isolation->lock($keyB);

      $lockA->release();
      $lockB->release();
    });

    self::addToAssertionCount(1);
  }
}
