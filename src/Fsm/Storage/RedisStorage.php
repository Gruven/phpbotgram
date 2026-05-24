<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Storage;

use Amp\Redis\Command\Option\SetOptions;
use Amp\Redis\RedisClient;
use Gruven\PhpBotGram\Fsm\State;
use JsonException;

use function Amp\Redis\createRedisClient;

/**
 * Redis-backed FSM storage using `amphp/redis ^2`.
 *
 * Mirrors `aiogram.fsm.storage.redis.RedisStorage`
 * (`aiogram/fsm/storage/redis.py`). State and data are stored as plain
 * Redis strings; data payloads are JSON-serialised.
 *
 * TTLs are expressed in seconds (matching the upstream `state_ttl` /
 * `data_ttl` `datetime.timedelta`-derived semantics). `null` means
 * no expiry — keys persist until explicitly deleted or the server is
 * flushed.
 *
 * The `fromUrl` factory mirrors upstream `RedisStorage.from_url` and
 * provides a convenient single-string construction path compatible with
 * any URI accepted by `Amp\Redis\RedisConfig::fromUri`:
 *   - `redis://[:password@]host[:port][/db]`
 *   - `tcp://host:port`
 *   - `unix:///path/to/socket`
 *
 * Key building delegates to the injected `KeyBuilder`; the default is
 * `DefaultKeyBuilder` (prefix `fsm`, colon-separated segments).
 */
final class RedisStorage extends BaseStorage
{
  /**
   * @param RedisClient $redis amphp/redis client instance.
   * @param KeyBuilder $keyBuilder Key-builder strategy.
   * @param null|int $stateTtl TTL in seconds for state keys, or `null` for no expiry.
   * @param null|int $dataTtl TTL in seconds for data keys, or `null` for no expiry.
   */
  public function __construct(
    private readonly RedisClient $redis,
    private readonly KeyBuilder $keyBuilder = new DefaultKeyBuilder(),
    private readonly ?int $stateTtl = null,
    private readonly ?int $dataTtl = null,
  ) {}

  // ------------------------------------------------------------------ //
  // Static factory
  // ------------------------------------------------------------------ //

  /**
   * Create a `RedisStorage` from a URI string.
   *
   * Mirrors `RedisStorage.from_url` (upstream `redis.py`). Internally
   * calls `Amp\Redis\createRedisClient()` which wraps
   * `ReconnectingRedisLink` + `SocketRedisConnector` derived from
   * `RedisConfig::fromUri($url)`.
   *
   * @param string $url Redis URI (e.g. `redis://localhost:6379/0`).
   * @param null|KeyBuilder $keyBuilder Optional key-builder override.
   * @param null|int $stateTtl TTL in seconds for state keys.
   * @param null|int $dataTtl TTL in seconds for data keys.
   */
  public static function fromUrl(
    string $url,
    ?KeyBuilder $keyBuilder = null,
    ?int $stateTtl = null,
    ?int $dataTtl = null,
  ): self {
    $client = createRedisClient($url);

    return new self(
      redis: $client,
      keyBuilder: $keyBuilder ?? new DefaultKeyBuilder(),
      stateTtl: $stateTtl,
      dataTtl: $dataTtl,
    );
  }

  // ------------------------------------------------------------------ //
  // BaseStorage implementation
  // ------------------------------------------------------------------ //

  /**
   * Persist the FSM state for the given key.
   *
   * Mirrors `RedisStorage.set_state` (upstream `redis.py`).
   *
   * - `$state === null`           → delete the key.
   * - `$state instanceof State`   → store `$state->state()`.
   * - `$state` is a plain string  → store as-is.
   *
   * @param StorageKey $key Storage address.
   * @param null|State|string $state New state value.
   */
  public function setState(StorageKey $key, State|string|null $state = null): void
  {
    $redisKey = $this->keyBuilder->build($key, StoragePart::State);

    if ($state === null) {
      $this->redis->delete($redisKey);

      return;
    }

    if ($state instanceof State) {
      $value = $state->state() ?? '';
    } else {
      $value = $state;
    }

    $options = new SetOptions();

    if ($this->stateTtl !== null) {
      $options = $options->withTtl($this->stateTtl);
    }

    $this->redis->set($redisKey, $value, $options);
  }

  /**
   * Retrieve the FSM state for the given key.
   *
   * Mirrors `RedisStorage.get_state` (upstream `redis.py`).
   *
   * @param StorageKey $key Storage address.
   *
   * @return null|string Stored state name, or `null` if none.
   */
  public function getState(StorageKey $key): ?string
  {
    $redisKey = $this->keyBuilder->build($key, StoragePart::State);

    return $this->redis->get($redisKey);
  }

  /**
   * Persist the FSM data payload for the given key.
   *
   * Mirrors `RedisStorage.set_data` (upstream `redis.py`).
   *
   * An empty array results in the Redis key being deleted (matching
   * upstream behaviour: `if not data: await self.redis.delete(redis_key)`).
   *
   * @param StorageKey $key Storage address.
   * @param array<string, mixed> $data Data map to store.
   *
   * @throws JsonException When JSON serialisation fails.
   */
  public function setData(StorageKey $key, array $data): void
  {
    $redisKey = $this->keyBuilder->build($key, StoragePart::Data);

    if ($data === []) {
      $this->redis->delete($redisKey);

      return;
    }

    $options = new SetOptions();

    if ($this->dataTtl !== null) {
      $options = $options->withTtl($this->dataTtl);
    }

    $this->redis->set($redisKey, json_encode($data, JSON_THROW_ON_ERROR), $options);
  }

  /**
   * Retrieve the FSM data payload for the given key.
   *
   * Mirrors `RedisStorage.get_data` (upstream `redis.py`).
   *
   * @param StorageKey $key Storage address.
   *
   * @return array<string, mixed> Current data map (empty array when no data stored).
   *
   * @throws JsonException When JSON decoding fails.
   */
  public function getData(StorageKey $key): array
  {
    $redisKey = $this->keyBuilder->build($key, StoragePart::Data);

    $value = $this->redis->get($redisKey);

    if ($value === null) {
      return [];
    }

    /** @var array<string, mixed> */
    return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
  }

  /**
   * Close the Redis connection.
   *
   * Mirrors `RedisStorage.close` (upstream `redis.py`).
   * Calls `quit()` on the client to gracefully disconnect.
   */
  public function close(): void
  {
    $this->redis->quit();
  }
}
