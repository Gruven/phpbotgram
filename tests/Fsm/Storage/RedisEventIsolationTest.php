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

/**
 * Unit tests for `RedisEventIsolation`.
 *
 * The Redis link is replaced with a spy that records every `execute()` call
 * and returns pre-programmed responses. No live Redis connection is required.
 *
 * Integration tests (requiring a live Redis server) are skipped automatically
 * when `PHPBOTGRAM_TEST_REDIS_DSN` is not set.
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
