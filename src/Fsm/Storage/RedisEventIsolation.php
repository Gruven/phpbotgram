<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Storage;

use Amp\Redis\Command\Option\SetOptions;
use Amp\Redis\RedisClient;
use RuntimeException;

use function Amp\delay;

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
   *                            forever.
   * @param null|int $acquireTimeoutSeconds Maximum wall-clock seconds the acquire
   *                                        loop will spin before giving up with a
   *                                        `RuntimeException`. Defaults to
   *                                        `$lockTtlSeconds * 2` so that a slow
   *                                        holder (still within its TTL) does not
   *                                        starve waiting callers.
   */
  public function __construct(
    private readonly RedisClient $redis,
    private readonly KeyBuilder $keyBuilder = new DefaultKeyBuilder(),
    private readonly int $lockTtlSeconds = 60,
    private readonly ?int $acquireTimeoutSeconds = null,
  ) {}

  // ------------------------------------------------------------------ //
  // Helpers
  // ------------------------------------------------------------------ //

  /**
   * Resolve the effective acquire-timeout budget in seconds.
   *
   * When `$acquireTimeoutSeconds` is `null` (the default), falls back to
   * `$lockTtlSeconds * 2` so that waiting callers outlast the holder's TTL.
   */
  private function acquireTimeout(): int
  {
    return $this->acquireTimeoutSeconds ?? ($this->lockTtlSeconds * 2);
  }

  // ------------------------------------------------------------------ //
  // BaseEventIsolation implementation
  // ------------------------------------------------------------------ //

  /**
   * Acquire a distributed lock for `$key`.
   *
   * Spins until `SET NX PX` succeeds or the acquire-timeout budget is exhausted.
   * Returns a `Lock` whose `release()` executes the safe Lua unlock script.
   *
   * @param StorageKey $key Storage address to lock.
   *
   * @return Lock Acquired lock; call `release()` in a `finally` block.
   *
   * @throws RuntimeException When the lock cannot be acquired within `$acquireTimeoutSeconds`
   *                          (or `$lockTtlSeconds * 2` when the former is `null`).
   */
  public function lock(StorageKey $key): Lock
  {
    $lockKey = $this->keyBuilder->build($key, StoragePart::Lock);
    $token = bin2hex(random_bytes(16));
    $ttlMs = $this->lockTtlSeconds * 1000;

    $acquireTimeout = $this->acquireTimeout();
    $deadline = microtime(true) + $acquireTimeout;

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
            $acquireTimeout,
          ),
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
