<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm\Storage;

use Amp\Redis\Connection\RedisLink;
use Amp\Redis\Protocol\RedisResponse;
use Amp\Redis\Protocol\RedisValue;
use Amp\Redis\RedisClient;
use Gruven\PhpBotGram\Fsm\State;
use Gruven\PhpBotGram\Fsm\Storage\BaseStorage;
use Gruven\PhpBotGram\Fsm\Storage\DefaultKeyBuilder;
use Gruven\PhpBotGram\Fsm\Storage\RedisStorage;
use Gruven\PhpBotGram\Fsm\Storage\StorageKey;
use Gruven\PhpBotGram\Fsm\Storage\StoragePart;
use Gruven\PhpBotGram\Tests\Support\RunAsyncTrait;
use PHPUnit\Framework\TestCase;

/**
 * Upstream `tests/test_fsm/storage/test_redis_mock.py` cases deliberately
 * not ported:
 *
 * - `TestRedisStorageMock::test_from_url` — API divergence: upstream patches
 *   `redis.asyncio.connection.ConnectionPool.from_url` with Python mock; PHP
 *   uses amphp/redis which has no equivalent factory URL at this level.
 * - `TestRedisStorageMock::test_close` — API divergence: `aclose()` is an
 *   asyncio method; PHP uses `quit` command on the AMPHP client.
 * - `TestRedisStorageMock::test_set_data_invalid` — covered by
 *   `BaseStorageTest` / DataNotDictLike contract; PHP validates upstream.
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 *
 * @internal
 */
final class RedisStorageTest extends TestCase
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
   * Build a `RedisClient` backed by a spy `RedisLink` that appends every
   * `execute()` call to `$this->calls`.
   *
   * @param array<string, null|string> $gets Canned values for `GET` commands.
   */
  private function makeRedis(array $gets = []): RedisClient
  {
    $test = $this;

    $link = new class ($gets, $test) implements RedisLink {
      /**
       * @param array<string, null|string> $gets
       */
      public function __construct(
        private readonly array $gets,
        private readonly RedisStorageTest $test,
      ) {}

      public function execute(string $command, array $parameters): RedisResponse
      {
        $cmd = strtolower($command);

        /** @var array<float|int|string> $parameters */
        $this->test->recordCall($cmd, $parameters);

        $firstArg = isset($parameters[0]) ? (string)$parameters[0] : '';

        return match ($cmd) {
          'get' => new RedisValue($this->gets[$firstArg] ?? null),
          'set' => new RedisValue('OK'),
          'del' => new RedisValue(1),
          'quit' => new RedisValue(null),
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

  public function testRedisStorageExtendsBaseStorage(): void
  {
    $storage = new RedisStorage($this->makeRedis());

    self::assertInstanceOf(BaseStorage::class, $storage);
  }

  // ------------------------------------------------------------------ //
  // setState
  // ------------------------------------------------------------------ //

  public function testSetStateNullDeletesKey(): void
  {
    $storage = new RedisStorage($this->makeRedis());

    $storage->setState($this->key, null);

    $expectedKey = (new DefaultKeyBuilder())->build($this->key, StoragePart::State);
    $delCalls = array_values(array_filter($this->calls, static fn($e) => $e['cmd'] === 'del'));
    self::assertCount(1, $delCalls);
    self::assertSame($expectedKey, (string)$delCalls[0]['args'][0]);
  }

  public function testSetStateStringStoresValue(): void
  {
    $storage = new RedisStorage($this->makeRedis());

    $storage->setState($this->key, 'MyGroup:idle');

    $expectedKey = (new DefaultKeyBuilder())->build($this->key, StoragePart::State);
    $setCalls = array_values(array_filter($this->calls, static fn($e) => $e['cmd'] === 'set'));
    self::assertCount(1, $setCalls);
    self::assertSame($expectedKey, (string)$setCalls[0]['args'][0]);
    self::assertSame('MyGroup:idle', (string)$setCalls[0]['args'][1]);
  }

  public function testSetStateWithStateObjectExtractsQualifiedName(): void
  {
    $storage = new RedisStorage($this->makeRedis());

    // State with explicit group name produces 'TestGroup:idle'.
    $state = new State('idle', 'TestGroup');

    $storage->setState($this->key, $state);

    $setCalls = array_values(array_filter($this->calls, static fn($e) => $e['cmd'] === 'set'));
    self::assertCount(1, $setCalls);
    self::assertSame('TestGroup:idle', (string)$setCalls[0]['args'][1]);
  }

  public function testSetStateForwardsStateTtl(): void
  {
    $storage = new RedisStorage($this->makeRedis(), stateTtl: 300);

    $storage->setState($this->key, 'active');

    $setCalls = array_values(array_filter($this->calls, static fn($e) => $e['cmd'] === 'set'));
    self::assertCount(1, $setCalls);
    $args = $setCalls[0]['args'];
    self::assertContains('EX', $args, 'Expected EX flag in SET args');
    self::assertContains(300, $args, 'Expected TTL value 300 in SET args');
  }

  public function testSetStateWithNullTtlOmitsExpiry(): void
  {
    $storage = new RedisStorage($this->makeRedis(), stateTtl: null);

    $storage->setState($this->key, 'active');

    $setCalls = array_values(array_filter($this->calls, static fn($e) => $e['cmd'] === 'set'));
    $args = $setCalls[0]['args'];
    self::assertNotContains('EX', $args, 'TTL flag must be absent when stateTtl is null');
  }

  // ------------------------------------------------------------------ //
  // getState
  // ------------------------------------------------------------------ //

  public function testGetStateReturnsStoredValue(): void
  {
    $expectedKey = (new DefaultKeyBuilder())->build($this->key, StoragePart::State);
    $storage = new RedisStorage($this->makeRedis([$expectedKey => 'active']));

    self::assertSame('active', $storage->getState($this->key));
  }

  public function testGetStateReturnsNullWhenAbsent(): void
  {
    $storage = new RedisStorage($this->makeRedis());

    self::assertNull($storage->getState($this->key));
  }

  // ------------------------------------------------------------------ //
  // setData
  // ------------------------------------------------------------------ //

  public function testSetDataEmptyArrayDeletesKey(): void
  {
    $storage = new RedisStorage($this->makeRedis());

    $storage->setData($this->key, []);

    $expectedKey = (new DefaultKeyBuilder())->build($this->key, StoragePart::Data);
    $delCalls = array_values(array_filter($this->calls, static fn($e) => $e['cmd'] === 'del'));
    self::assertCount(1, $delCalls);
    self::assertSame($expectedKey, (string)$delCalls[0]['args'][0]);
  }

  public function testSetDataNonEmptyStoresJsonPayload(): void
  {
    $storage = new RedisStorage($this->makeRedis());
    $data = ['name' => 'Alice', 'step' => 3];

    $storage->setData($this->key, $data);

    $setCalls = array_values(array_filter($this->calls, static fn($e) => $e['cmd'] === 'set'));
    self::assertCount(1, $setCalls);
    self::assertSame(
      json_encode($data, JSON_THROW_ON_ERROR),
      (string)$setCalls[0]['args'][1],
    );
  }

  public function testDefaultKeyBuilderStoresSceneHistoryDestiny(): void
  {
    $storage = new RedisStorage($this->makeRedis());
    $historyKey = $this->key->withDestiny('scenes_history');

    $storage->setData($historyKey, ['history' => []]);

    $setCalls = array_values(array_filter($this->calls, static fn($e) => $e['cmd'] === 'set'));
    self::assertCount(1, $setCalls);
    self::assertSame('fsm:100:42:scenes_history:data', (string)$setCalls[0]['args'][0]);
  }

  public function testSetDataForwardsDataTtl(): void
  {
    $storage = new RedisStorage($this->makeRedis(), dataTtl: 600);

    $storage->setData($this->key, ['x' => 1]);

    $setCalls = array_values(array_filter($this->calls, static fn($e) => $e['cmd'] === 'set'));
    $args = $setCalls[0]['args'];
    self::assertContains('EX', $args);
    self::assertContains(600, $args);
  }

  public function testSetDataWithNullTtlOmitsExpiry(): void
  {
    $storage = new RedisStorage($this->makeRedis(), dataTtl: null);

    $storage->setData($this->key, ['x' => 1]);

    $setCalls = array_values(array_filter($this->calls, static fn($e) => $e['cmd'] === 'set'));
    $args = $setCalls[0]['args'];
    self::assertNotContains('EX', $args);
  }

  // ------------------------------------------------------------------ //
  // getData
  // ------------------------------------------------------------------ //

  public function testGetDataReturnsEmptyArrayWhenKeyAbsent(): void
  {
    $storage = new RedisStorage($this->makeRedis());

    self::assertSame([], $storage->getData($this->key));
  }

  public function testGetDataDecodesJsonPayload(): void
  {
    $payload = ['name' => 'Bob', 'count' => 7];
    $expectedKey = (new DefaultKeyBuilder())->build($this->key, StoragePart::Data);
    $storage = new RedisStorage(
      $this->makeRedis([$expectedKey => json_encode($payload, JSON_THROW_ON_ERROR)]),
    );

    self::assertSame($payload, $storage->getData($this->key));
  }

  // ------------------------------------------------------------------ //
  // getState — string (not bytes) response
  // ------------------------------------------------------------------ //

  /**
   * `getState` returns the value as-is when Redis returns a plain string
   * (not bytes).
   *
   * Mirrors `TestRedisStorageMock::test_get_state_str`.
   */
  public function testGetStateReturnsStringDirectly(): void
  {
    $expectedKey = (new DefaultKeyBuilder())->build($this->key, StoragePart::State);
    $storage = new RedisStorage($this->makeRedis([$expectedKey => 'test_state']));

    self::assertSame('test_state', $storage->getState($this->key));
  }

  // ------------------------------------------------------------------ //
  // getValue — base implementation delegation
  // ------------------------------------------------------------------ //

  /**
   * `getValue` uses the base implementation and returns the dict key value.
   *
   * Mirrors `TestRedisStorageMock::test_get_value_uses_base_implementation`.
   */
  public function testGetValueUsesBaseImplementation(): void
  {
    $payload = ['foo' => 'bar'];
    $expectedKey = (new DefaultKeyBuilder())->build($this->key, StoragePart::Data);
    $storage = new RedisStorage(
      $this->makeRedis([$expectedKey => json_encode($payload, JSON_THROW_ON_ERROR)]),
    );

    self::assertSame('bar', $storage->getValue($this->key, 'foo'));
  }

  /**
   * `getValue` returns the default when the key is absent.
   *
   * Mirrors `TestRedisStorageMock::test_get_value_default`.
   */
  public function testGetValueReturnsDefaultWhenKeyAbsent(): void
  {
    $storage = new RedisStorage($this->makeRedis());

    self::assertSame('x', $storage->getValue($this->key, 'missing', 'x'));
  }

  // ------------------------------------------------------------------ //
  // close
  // ------------------------------------------------------------------ //

  public function testCloseCallsQuit(): void
  {
    $storage = new RedisStorage($this->makeRedis());

    $storage->close();

    $quitCalls = array_values(array_filter($this->calls, static fn($e) => $e['cmd'] === 'quit'));
    self::assertCount(1, $quitCalls);
  }

  // ------------------------------------------------------------------ //
  // Integration tests (skip if no DSN)
  // ------------------------------------------------------------------ //

  public function testIntegrationStateRoundTrip(): void
  {
    $dsn = getenv('PHPBOTGRAM_TEST_REDIS_DSN');

    if (!$dsn) {
      $this->markTestSkipped('PHPBOTGRAM_TEST_REDIS_DSN not set; skipping live redis tests');
    }

    $this->runAsync(static function () use ($dsn): void {
      $storage = RedisStorage::fromUrl((string)$dsn);
      $key = new StorageKey(botId: 999, chatId: 888, userId: 777);

      try {
        $storage->setState($key, 'integration:state');
        self::assertSame('integration:state', $storage->getState($key));

        $storage->setState($key, null);
        self::assertNull($storage->getState($key));
      } finally {
        $storage->setState($key, null);
        $storage->setData($key, []);
        $storage->close();
      }
    });
  }

  public function testIntegrationDataRoundTrip(): void
  {
    $dsn = getenv('PHPBOTGRAM_TEST_REDIS_DSN');

    if (!$dsn) {
      $this->markTestSkipped('PHPBOTGRAM_TEST_REDIS_DSN not set; skipping live redis tests');
    }

    $this->runAsync(static function () use ($dsn): void {
      $storage = RedisStorage::fromUrl((string)$dsn);
      $key = new StorageKey(botId: 999, chatId: 888, userId: 777);

      try {
        $storage->setData($key, ['foo' => 'bar', 'num' => 42]);
        self::assertSame(['foo' => 'bar', 'num' => 42], $storage->getData($key));

        $storage->setData($key, []);
        self::assertSame([], $storage->getData($key));
      } finally {
        $storage->setState($key, null);
        $storage->setData($key, []);
        $storage->close();
      }
    });
  }
}
