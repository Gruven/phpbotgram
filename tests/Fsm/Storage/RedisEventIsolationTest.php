<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm\Storage;

use Amp\Redis\Connection\RedisLink;
use Amp\Redis\Protocol\RedisError;
use Amp\Redis\Protocol\RedisResponse;
use Amp\Redis\Protocol\RedisValue;
use Amp\Redis\RedisClient;
use Gruven\PhpBotGram\Fsm\Storage\BaseEventIsolation;
use Gruven\PhpBotGram\Fsm\Storage\DefaultKeyBuilder;
use Gruven\PhpBotGram\Fsm\Storage\Lock;
use Gruven\PhpBotGram\Fsm\Storage\RedisEventIsolation;
use Gruven\PhpBotGram\Fsm\Storage\RedisStorage;
use Gruven\PhpBotGram\Fsm\Storage\StorageKey;
use Gruven\PhpBotGram\Fsm\Storage\StoragePart;
use Gruven\PhpBotGram\Tests\Support\RunAsyncTrait;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Upstream `tests/test_fsm/storage/test_isolation.py` and
 * `tests/test_fsm/storage/test_redis_mock.py::TestRedisEventIsolationLockMock`
 * cases deliberately not ported:
 *
 * - `TestIsolations::test_lock` parametrize rows `redis_isolation`,
 *   `lock_isolation`, `disabled_isolation` — these are parameterized fixtures
 *   requiring pytest fixture injection; `redis_isolation` needs a live Redis
 *   server (live-service required) and `lock_isolation`/`disabled_isolation`
 *   are covered by `EventIsolationTest`.
 * - `TestRedisEventIsolation::test_create_isolation` — API divergence: PHP
 *   `RedisStorage` does not expose a `create_isolation()` factory method; the
 *   isolation is constructed explicitly via `new RedisEventIsolation($client)`.
 * - `TestRedisEventIsolation::test_init_without_key_builder` — covered
 *   behaviorally: constructor uses a default `DefaultKeyBuilder`; tested
 *   implicitly by `testLockKeyBuiltWithLockPart`.
 * - `TestRedisEventIsolation::test_create_from_url` — API divergence: PHP
 *   uses `amphp/redis` DSN factory (`createRedisClient()`), not a Python
 *   `ConnectionPool.from_url` patch.
 * - `TestRedisEventIsolation::test_close` — covered by `testCloseIsNoOp`.
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 */
final class RedisEventIsolationTest extends TestCase
{
  use RunAsyncTrait;

  private StorageKey $key;

  /** @var list<array{cmd: string, args: array<float|int|string>}> */
  private array $calls = [];

  protected function setUp(): void
  {
    $this->key = new StorageKey(botId: 1, chatId: 100, userId: 42);
    $this->calls = [];
  }

  // ------------------------------------------------------------------ //
  // Helpers
  // ------------------------------------------------------------------ //

  /**
   * Build a spy `RedisClient`.
   *
   * `SET NX` commands return `'OK'` (acquired) or `null` (contended).
   * `EVALSHA` returns a NOSCRIPT error so `RedisClient::eval()` falls back
   * to plain `EVAL`. `EVAL` returns `1` (unlock success).
   *
   * Every `execute()` call is appended to `$this->calls`.
   *
   * @param bool $setNxResult `true` → SET NX returns 'OK'; `false` → `null`.
   */
  private function makeRedis(bool $setNxResult = true): RedisClient
  {
    $test = $this;

    $link = new class ($setNxResult, $test) implements RedisLink {
      public function __construct(
        private readonly bool $setNxResult,
        private readonly RedisEventIsolationTest $test,
      ) {}

      public function execute(string $command, array $parameters): RedisResponse
      {
        $cmd = strtolower($command);

        /** @var array<float|int|string> $parameters */
        $this->test->recordCall($cmd, $parameters);

        return match ($cmd) {
          // SET NX: 'OK' when acquired, null when key already exists.
          'set' => new RedisValue($this->setNxResult ? 'OK' : null),
          // Lua unlock script returns 1 (deleted) or 0 (not owner).
          'eval' => new RedisValue(1),
          // EVALSHA triggers NOSCRIPT → RedisClient falls back to EVAL.
          'evalsha' => new RedisError('NOSCRIPT No matching script. Please use EVAL.'),
          default => new RedisValue(null),
        };
      }
    };

    return new RedisClient($link);
  }

  /**
   * Called by the anonymous spy link to append a call entry.
   *
   * Public so that the anonymous inner class can reference it.
   *
   * @param array<float|int|string> $args
   */
  public function recordCall(string $cmd, array $args): void
  {
    $this->calls[] = ['cmd' => $cmd, 'args' => $args];
  }

  // ------------------------------------------------------------------ //
  // Structural checks
  // ------------------------------------------------------------------ //

  public function testRedisEventIsolationExtendsBase(): void
  {
    $isolation = new RedisEventIsolation($this->makeRedis());

    self::assertInstanceOf(BaseEventIsolation::class, $isolation);
  }

  /**
   * Constructor accepts an explicit key builder; the provided builder is used.
   *
   * Mirrors `TestRedisEventIsolation::test_init_with_key_builder`.
   */
  public function testInitWithExplicitKeyBuilderUsesIt(): void
  {
    $redis = $this->makeRedis();
    $kb = new DefaultKeyBuilder(prefix: 'myapp');
    $isolation = new RedisEventIsolation($redis, keyBuilder: $kb);

    // The isolation must use the custom key builder, reflected in the lock key.
    $lock = $this->runAsync(function () use ($isolation): Lock {
      return $isolation->lock($this->key);
    });

    $expectedKey = $kb->build($this->key, StoragePart::Lock);
    $setCalls = array_values(array_filter($this->calls, static fn($e) => $e['cmd'] === 'set'));
    self::assertSame($expectedKey, (string)$setCalls[0]['args'][0]);

    $lock->release();
  }

  // ------------------------------------------------------------------ //
  // lock() — acquire side
  // ------------------------------------------------------------------ //

  public function testLockReturnsLockInstance(): void
  {
    $isolation = new RedisEventIsolation($this->makeRedis());

    $lock = $this->runAsync(static function () use ($isolation): Lock {
      $key = new StorageKey(botId: 1, chatId: 100, userId: 42);

      return $isolation->lock($key);
    });

    self::assertInstanceOf(Lock::class, $lock);

    $lock->release();
  }

  public function testLockIssuesSetNxPxCommand(): void
  {
    $isolation = new RedisEventIsolation($this->makeRedis());

    $lock = $this->runAsync(function () use ($isolation): Lock {
      return $isolation->lock($this->key);
    });

    $setCalls = array_values(array_filter($this->calls, static fn($e) => $e['cmd'] === 'set'));
    self::assertGreaterThanOrEqual(1, count($setCalls), 'lock() must issue at least one SET command');

    $args = $setCalls[0]['args'];
    self::assertContains('NX', $args, 'SET must include NX flag');
    self::assertContains('PX', $args, 'SET must include PX (millisecond TTL) flag');

    $lock->release();
  }

  public function testLockKeyBuiltWithLockPart(): void
  {
    $isolation = new RedisEventIsolation($this->makeRedis());

    $lock = $this->runAsync(function () use ($isolation): Lock {
      return $isolation->lock($this->key);
    });

    $expectedKey = (new DefaultKeyBuilder())->build($this->key, StoragePart::Lock);
    $setCalls = array_values(array_filter($this->calls, static fn($e) => $e['cmd'] === 'set'));
    self::assertSame($expectedKey, (string)$setCalls[0]['args'][0], 'Lock key must use StoragePart::Lock');

    $lock->release();
  }

  public function testLockTtlIsForwardedAsPxMilliseconds(): void
  {
    $isolation = new RedisEventIsolation($this->makeRedis(), lockTtlSeconds: 30);

    $lock = $this->runAsync(function () use ($isolation): Lock {
      return $isolation->lock($this->key);
    });

    $setCalls = array_values(array_filter($this->calls, static fn($e) => $e['cmd'] === 'set'));
    $args = $setCalls[0]['args'];
    $pxIdx = array_search('PX', $args, true);
    self::assertNotFalse($pxIdx, 'PX flag must appear in args');
    self::assertSame(30_000, $args[(int)$pxIdx + 1], 'PX value must be lockTtlSeconds * 1000');

    $lock->release();
  }

  /**
   * When `$acquireTimeoutSeconds` is not provided, the default acquire timeout
   * must be `$lockTtlSeconds * 2`.
   *
   * We verify this via the exception message: the acquire loop gives up with an
   * error containing the resolved timeout value. Using `lockTtlSeconds: 5` and
   * `acquireTimeoutSeconds: 0` isolates which parameter drives the message — the
   * message must say "within 10 seconds" (5 * 2), proving the default formula
   * applies when the parameter is omitted.
   *
   * We use a separate isolation instance with `acquireTimeoutSeconds: 0` to keep
   * the test near-instant (deadline is in the past on the first retry after one
   * 50 ms delay).
   */
  public function testAcquireTimeoutDefaultsToDoubleLockTtl(): void
  {
    // Verify the formula via reflection — no spinning needed.
    $isolation = new RedisEventIsolation(
      $this->makeRedis(),
      lockTtlSeconds: 5,
      // acquireTimeoutSeconds deliberately omitted
    );

    $ref = new ReflectionClass($isolation);
    $method = $ref->getMethod('acquireTimeout');

    self::assertSame(10, $method->invoke($isolation), 'Default acquireTimeout must be lockTtlSeconds * 2');
  }

  /**
   * When `$acquireTimeoutSeconds` is supplied explicitly it overrides the
   * `lockTtlSeconds * 2` default, both for the deadline and for the exception
   * message when the budget is exhausted.
   *
   * The spy always returns `null` for SET NX (contended lock), so the loop
   * exhausts the budget after at most one `delay(0.05)` iteration.
   */
  public function testAcquireTimeoutExplicitOverride(): void
  {
    $isolation = new RedisEventIsolation(
      $this->makeRedis(setNxResult: false),
      lockTtlSeconds: 60,
      acquireTimeoutSeconds: 0,
    );

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('within 0 seconds');

    $this->runAsync(function () use ($isolation): void {
      $isolation->lock($this->key);
    });
  }

  // ------------------------------------------------------------------ //
  // release() — safe unlock side
  // ------------------------------------------------------------------ //

  public function testReleaseInvokesEvalWithUnlockScript(): void
  {
    $isolation = new RedisEventIsolation($this->makeRedis());

    $lock = $this->runAsync(function () use ($isolation): Lock {
      return $isolation->lock($this->key);
    });

    $countBefore = count($this->calls);
    $lock->release();

    // After release, either 'evalsha' (first attempt) or 'eval' (fallback) must appear.
    $evalCalls = array_values(array_filter(
      array_slice($this->calls, $countBefore),
      static fn($e) => in_array($e['cmd'], ['eval', 'evalsha'], true)
    ));
    self::assertGreaterThanOrEqual(1, count($evalCalls), 'release() must invoke eval for safe unlock');
  }

  public function testReleaseIsIdempotent(): void
  {
    $isolation = new RedisEventIsolation($this->makeRedis());

    $lock = $this->runAsync(function () use ($isolation): Lock {
      return $isolation->lock($this->key);
    });

    $lock->release();
    $countAfterFirst = count($this->calls);

    // Second release must be a no-op (the Lock::$released guard prevents re-entry).
    $lock->release();

    self::assertSame(
      $countAfterFirst,
      count($this->calls),
      'Second release() must not issue additional Redis commands'
    );
  }

  public function testReleasePassesTokenToEvalArgs(): void
  {
    $isolation = new RedisEventIsolation($this->makeRedis());

    $lock = $this->runAsync(function () use ($isolation): Lock {
      return $isolation->lock($this->key);
    });

    // Capture the token from the SET call (args[1]).
    $setCalls = array_values(array_filter($this->calls, static fn($e) => $e['cmd'] === 'set'));
    $token = (string)$setCalls[0]['args'][1];

    $lock->release();

    // The EVAL call (or EVALSHA fallback) should carry the same token as ARGV[1].
    $evalCalls = array_values(array_filter(
      $this->calls,
      static fn($e) => in_array($e['cmd'], ['eval', 'evalsha'], true)
    ));
    self::assertNotEmpty($evalCalls, 'At least one eval call must have been recorded');
    $lastEvalCall = $evalCalls[count($evalCalls) - 1];
    self::assertContains($token, $lastEvalCall['args'], 'EVAL must pass the lock token for safe check-and-delete');
  }

  // ------------------------------------------------------------------ //
  // close()
  // ------------------------------------------------------------------ //

  public function testCloseIsNoOp(): void
  {
    $isolation = new RedisEventIsolation($this->makeRedis());

    $isolation->close();

    // close() must not issue any Redis commands.
    self::assertSame([], $this->calls);
  }

  // ------------------------------------------------------------------ //
  // Integration tests (skip if no DSN)
  // ------------------------------------------------------------------ //

  public function testIntegrationLockSerializesConcurrentAccess(): void
  {
    $dsn = getenv('PHPBOTGRAM_TEST_REDIS_DSN');

    if (!$dsn) {
      $this->markTestSkipped('PHPBOTGRAM_TEST_REDIS_DSN not set; skipping live redis tests');
    }

    $storage = RedisStorage::fromUrl((string)$dsn);
    $isolation = new RedisEventIsolation(\Amp\Redis\createRedisClient((string)$dsn));

    try {
      // Acquire the lock.
      $lock = $isolation->lock($this->key);
      self::assertInstanceOf(Lock::class, $lock);

      // Must be able to release cleanly.
      $lock->release();

      // After release, re-acquire must succeed.
      $lock2 = $isolation->lock($this->key);
      $lock2->release();
    } finally {
      $storage->close();
      $isolation->close();
    }
  }
}
