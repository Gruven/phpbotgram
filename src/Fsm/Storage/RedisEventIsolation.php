<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Storage;

use function Amp\delay;

use Amp\Redis\Command\Option\SetOptions;
use Amp\Redis\RedisClient;
use RuntimeException;

/**
 * Redis-backed FSM event isolation via a distributed SET NX PX lock.
 *
 * Mirrors `aiogram.fsm.storage.redis.RedisEventIsolation`
 * (`aiogram/fsm/storage/redis.py`). Because `amphp/redis ^2` does not
 * expose a built-in distributed lock primitive with an async-context-manager
 * interface, this class implements the lock protocol manually:
 *
 * **Acquire** — `SET $lockKey $token NX PX $ttlMs`
 *   - `NX` ensures only one client sets the key (atomic compare-and-set).
 *   - `PX` (millisecond TTL) guarantees the lock auto-expires if the holder
 *     crashes, preventing deadlocks.
 *   - On failure the caller sleeps 50 ms and retries until the TTL budget is
 *     consumed, after which a `RuntimeException` is thrown.
 *
 * **Release** — Lua `CHECK-AND-DELETE`
 *   ```lua
 *   if redis.call('get', KEYS[1]) == ARGV[1] then
 *       return redis.call('del', KEYS[1])
 *   else
 *       return 0
 *   end
 *   ```
 *   This atomically ensures that only the token owner can delete the key,
 *   preventing accidental release of a lock acquired by another client after
 *   the original lock expired.
 *
 * The lock is surfaced as a `Lock` value-object whose `release()` wires the
 * Lua check-and-delete via an optional `$releaseFn` closure (Task 5.3
 * extension).
 *
 * `close()` is a no-op: the owning `RedisStorage` manages the connection
 * lifecycle.
 */
final class RedisEventIsolation extends BaseEventIsolation
{
  /**
   * Lua script for safe lock release.
   *
   * Only deletes the key when the stored value matches the caller's token,
   * preventing a client from releasing a lock it no longer owns (e.g. after
   * TTL expiry and re-acquisition by another client).
   */
  private const UNLOCK_SCRIPT = <<<'LUA'
if redis.call('get', KEYS[1]) == ARGV[1] then
    return redis.call('del', KEYS[1])
else
    return 0
end
LUA;

  /**
   * Poll interval in seconds (50 ms) between acquire retries.
   */
  private const RETRY_DELAY_SECONDS = 0.05;

  /**
   * @param RedisClient $redis amphp/redis client instance (shared with storage).
   * @param KeyBuilder $keyBuilder Key-builder strategy; defaults to `DefaultKeyBuilder`.
   * @param int $lockTtlSeconds Lock TTL in seconds. After this duration the lock
   *                            auto-expires so a crashed holder cannot block others
   *                            forever.  Also used as the maximum wait time before
   *                            the acquire loop gives up.
   */
  public function __construct(
    private readonly RedisClient $redis,
    private readonly KeyBuilder $keyBuilder = new DefaultKeyBuilder(),
    private readonly int $lockTtlSeconds = 60,
  ) {}

  // ------------------------------------------------------------------ //
  // BaseEventIsolation implementation
  // ------------------------------------------------------------------ //

  /**
   * Acquire a distributed lock for `$key`.
   *
   * Spins until `SET NX PX` succeeds or the TTL budget is exhausted.
   * Returns a `Lock` whose `release()` executes the safe Lua unlock script.
   *
   * @param StorageKey $key Storage address to lock.
   *
   * @return Lock Acquired lock; call `release()` in a `finally` block.
   *
   * @throws RuntimeException When the lock cannot be acquired within `$lockTtlSeconds`.
   */
  public function lock(StorageKey $key): Lock
  {
    $lockKey = $this->keyBuilder->build($key, StoragePart::Lock);
    $token = bin2hex(random_bytes(16));
    $ttlMs = $this->lockTtlSeconds * 1000;

    $deadline = microtime(true) + $this->lockTtlSeconds;

    // SET key token NX PX ttlMs — returns true when the key was newly set,
    // false when the key already exists (NX = set only if Not eXists).
    $options = (new SetOptions())
      ->withTtlInMillis($ttlMs)
      ->withoutOverwrite();  // NX

    while (!$this->redis->set($lockKey, $token, $options)) {
      if (microtime(true) >= $deadline) {
        throw new RuntimeException(
          sprintf(
            'Failed to acquire Redis lock for key "%s" within %d seconds.',
            $lockKey,
            $this->lockTtlSeconds,
          )
        );
      }

      delay(self::RETRY_DELAY_SECONDS);
    }

    $redis = $this->redis;

    $releaseFn = static function () use ($redis, $lockKey, $token): void {
      $redis->eval(self::UNLOCK_SCRIPT, [$lockKey], [$token]);
    };

    return new Lock(inner: null, releaseFn: $releaseFn);
  }

  /**
   * No-op — the `RedisClient` connection is owned by the storage layer.
   *
   * Mirrors `RedisEventIsolation.close` (upstream `redis.py`).
   */
  public function close(): void
  {
    // Intentionally empty: connection lifecycle is managed by the
    // RedisStorage instance that shares the same RedisClient.
  }
}
