<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm\Storage;

use Gruven\PhpBotGram\Fsm\Storage\DefaultKeyBuilder;
use Gruven\PhpBotGram\Fsm\Storage\StorageKey;
use Gruven\PhpBotGram\Fsm\Storage\StoragePart;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Upstream `tests/test_fsm/storage/test_key_builder.py` cases deliberately
 * not ported:
 *
 * - No deliberate skips. All `TestDefaultKeyBuilder` cases (8 parametrize rows
 *   of `test_generate_key`, `test_destiny_check`, and `test_thread_id`) are
 *   fully ported in this file.
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 *
 * @internal
 */
final class DefaultKeyBuilderTest extends TestCase
{
  private const string PREFIX = 'test';
  private const int BOT_ID = 42;
  private const int CHAT_ID = -1;
  private const int USER_ID = 2;
  private const int THREAD_ID = 3;
  private const string BUSINESS_CONNECTION_ID = '4';

  private function makeKey(
    ?int $threadId = null,
    ?string $businessConnectionId = null,
    string $destiny = StorageKey::DEFAULT_DESTINY,
  ): StorageKey {
    return new StorageKey(
      botId: self::BOT_ID,
      chatId: self::CHAT_ID,
      userId: self::USER_ID,
      threadId: $threadId,
      businessConnectionId: $businessConnectionId,
      destiny: $destiny,
    );
  }

  /**
   * All flags on + part: `test:42:4:-1:2:default:data`.
   *
   * Upstream row 1 of `test_generate_key` parametrize.
   */
  public function testAllFlagsWithPart(): void
  {
    $builder = new DefaultKeyBuilder(
      prefix: self::PREFIX,
      withBotId: true,
      withDestiny: true,
      withBusinessConnectionId: true,
    );
    $key = $this->makeKey(businessConnectionId: self::BUSINESS_CONNECTION_ID);

    self::assertSame(
      self::PREFIX . ':' . self::BOT_ID . ':' . self::BUSINESS_CONNECTION_ID . ':' . self::CHAT_ID . ':' . self::USER_ID . ':' . StorageKey::DEFAULT_DESTINY . ':data',
      $builder->build($key, StoragePart::Data),
    );
  }

  /**
   * Bot ID + destiny, no part: `test:42:-1:2:default`.
   *
   * Upstream row 2 of `test_generate_key` parametrize.
   */
  public function testBotIdAndDestinyNoPart(): void
  {
    $builder = new DefaultKeyBuilder(prefix: self::PREFIX, withBotId: true, withDestiny: true);
    $key = $this->makeKey();

    self::assertSame(
      self::PREFIX . ':' . self::BOT_ID . ':' . self::CHAT_ID . ':' . self::USER_ID . ':' . StorageKey::DEFAULT_DESTINY,
      $builder->build($key, null),
    );
  }

  /**
   * Bot ID + business connection, no destiny, with part: `test:42:4:-1:2:data`.
   *
   * Upstream row 3 of `test_generate_key` parametrize.
   */
  public function testBotIdAndBusinessConnectionNoPart(): void
  {
    $builder = new DefaultKeyBuilder(
      prefix: self::PREFIX,
      withBotId: true,
      withBusinessConnectionId: true,
    );
    $key = $this->makeKey(businessConnectionId: self::BUSINESS_CONNECTION_ID);

    self::assertSame(
      self::PREFIX . ':' . self::BOT_ID . ':' . self::BUSINESS_CONNECTION_ID . ':' . self::CHAT_ID . ':' . self::USER_ID . ':data',
      $builder->build($key, StoragePart::Data),
    );
  }

  /**
   * Bot ID only, no part: `test:42:-1:2`.
   *
   * Upstream row 4 of `test_generate_key` parametrize.
   */
  public function testBotIdOnlyNoPart(): void
  {
    $builder = new DefaultKeyBuilder(prefix: self::PREFIX, withBotId: true);
    $key = $this->makeKey();

    self::assertSame(
      self::PREFIX . ':' . self::BOT_ID . ':' . self::CHAT_ID . ':' . self::USER_ID,
      $builder->build($key, null),
    );
  }

  /**
   * Destiny + business connection, no bot ID, with part: `test:4:-1:2:default:data`.
   *
   * Upstream row 5 of `test_generate_key` parametrize.
   */
  public function testDestinyAndBusinessConnectionNoBotId(): void
  {
    $builder = new DefaultKeyBuilder(
      prefix: self::PREFIX,
      withDestiny: true,
      withBusinessConnectionId: true,
    );
    $key = $this->makeKey(businessConnectionId: self::BUSINESS_CONNECTION_ID);

    self::assertSame(
      self::PREFIX . ':' . self::BUSINESS_CONNECTION_ID . ':' . self::CHAT_ID . ':' . self::USER_ID . ':' . StorageKey::DEFAULT_DESTINY . ':data',
      $builder->build($key, StoragePart::Data),
    );
  }

  /**
   * Destiny only, no part: `test:-1:2:default`.
   *
   * Upstream row 6 of `test_generate_key` parametrize.
   */
  public function testDestinyOnlyNoPart(): void
  {
    $builder = new DefaultKeyBuilder(prefix: self::PREFIX, withDestiny: true);
    $key = $this->makeKey();

    self::assertSame(
      self::PREFIX . ':' . self::CHAT_ID . ':' . self::USER_ID . ':' . StorageKey::DEFAULT_DESTINY,
      $builder->build($key, null),
    );
  }

  /**
   * Business connection only, no bot ID or destiny, with part: `test:4:-1:2:data`.
   *
   * Upstream row 7 of `test_generate_key` parametrize.
   */
  public function testBusinessConnectionOnlyWithPart(): void
  {
    $builder = new DefaultKeyBuilder(prefix: self::PREFIX, withBusinessConnectionId: true);
    $key = $this->makeKey(businessConnectionId: self::BUSINESS_CONNECTION_ID);

    self::assertSame(
      self::PREFIX . ':' . self::BUSINESS_CONNECTION_ID . ':' . self::CHAT_ID . ':' . self::USER_ID . ':data',
      $builder->build($key, StoragePart::Data),
    );
  }

  /**
   * Default config (prefix only), no part: `test:-1:2`.
   *
   * Upstream row 8 of `test_generate_key` parametrize.
   */
  public function testDefaultConfigNoPart(): void
  {
    $builder = new DefaultKeyBuilder(prefix: self::PREFIX);
    $key = $this->makeKey();

    self::assertSame(
      self::PREFIX . ':' . self::CHAT_ID . ':' . self::USER_ID,
      $builder->build($key, null),
    );
  }

  /**
   * Default config with the `fsm` prefix (constructor defaults).
   */
  public function testDefaultConfigWithFsmPrefix(): void
  {
    $builder = new DefaultKeyBuilder();
    $key = new StorageKey(botId: 1, chatId: 2, userId: 3);

    self::assertSame('fsm:2:3', $builder->build($key));
  }

  /**
   * Destiny check: key with default destiny + `withDestiny=false` succeeds.
   *
   * Mirrors `test_destiny_check` first assertion (`assert key_builder.build(key, FIELD)`).
   */
  public function testDefaultDestinyWithWithDestinyFalseSucceeds(): void
  {
    $builder = new DefaultKeyBuilder(withDestiny: false);
    $key = new StorageKey(botId: self::BOT_ID, chatId: self::CHAT_ID, userId: self::USER_ID);

    self::assertNotEmpty($builder->build($key, StoragePart::Data));
  }

  /**
   * Destiny check: key with non-default destiny + `withDestiny=false` throws.
   *
   * Mirrors `test_destiny_check` second assertion (expects `ValueError`; PHP maps to
   * `InvalidArgumentException` as the contract-violation equivalent).
   */
  public function testNonDefaultDestinyWithWithDestinyFalseThrows(): void
  {
    $builder = new DefaultKeyBuilder(withDestiny: false);
    $key = new StorageKey(
      botId: self::BOT_ID,
      chatId: self::CHAT_ID,
      userId: self::USER_ID,
      destiny: 'CUSTOM_TEST_DESTINY',
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/withDestiny/');

    $builder->build($key, StoragePart::Data);
  }

  /**
   * Non-default destiny accepted when `withDestiny=true`.
   */
  public function testNonDefaultDestinyWithWithDestinyTrueSucceeds(): void
  {
    $builder = new DefaultKeyBuilder(prefix: self::PREFIX, withDestiny: true);
    $key = new StorageKey(
      botId: self::BOT_ID,
      chatId: self::CHAT_ID,
      userId: self::USER_ID,
      destiny: 'custom',
    );

    self::assertSame(
      self::PREFIX . ':' . self::CHAT_ID . ':' . self::USER_ID . ':custom',
      $builder->build($key, null),
    );
  }

  /**
   * Thread ID appears between chatId and userId when set.
   *
   * Mirrors `test_thread_id` in upstream `test_key_builder.py:95-106`.
   */
  public function testThreadIdIsInsertedBetweenChatAndUser(): void
  {
    $builder = new DefaultKeyBuilder(prefix: self::PREFIX);
    $key = new StorageKey(
      botId: self::BOT_ID,
      chatId: self::CHAT_ID,
      userId: self::USER_ID,
      threadId: self::THREAD_ID,
      destiny: StorageKey::DEFAULT_DESTINY,
    );

    self::assertSame(
      self::PREFIX . ':' . self::CHAT_ID . ':' . self::THREAD_ID . ':' . self::USER_ID . ':data',
      $builder->build($key, StoragePart::Data),
    );
  }

  /**
   * Thread ID is omitted when `StorageKey::$threadId` is `null`.
   */
  public function testThreadIdOmittedWhenNull(): void
  {
    $builder = new DefaultKeyBuilder(prefix: self::PREFIX);
    $key = new StorageKey(botId: self::BOT_ID, chatId: self::CHAT_ID, userId: self::USER_ID);

    self::assertSame(
      self::PREFIX . ':' . self::CHAT_ID . ':' . self::USER_ID . ':data',
      $builder->build($key, StoragePart::Data),
    );
  }

  /**
   * `StoragePart::State` produces the `state` suffix.
   */
  public function testStoragePartStateProducesStateSuffix(): void
  {
    $builder = new DefaultKeyBuilder(prefix: self::PREFIX);
    $key = $this->makeKey();

    self::assertStringEndsWith(':state', $builder->build($key, StoragePart::State));
  }

  /**
   * `StoragePart::Lock` produces the `lock` suffix.
   */
  public function testStoragePartLockProducesLockSuffix(): void
  {
    $builder = new DefaultKeyBuilder(prefix: self::PREFIX);
    $key = $this->makeKey();

    self::assertStringEndsWith(':lock', $builder->build($key, StoragePart::Lock));
  }

  /**
   * Business connection ID is omitted when key's `businessConnectionId` is `null`,
   * even when `withBusinessConnectionId=true`.
   */
  public function testBusinessConnectionIdOmittedWhenNullOnKey(): void
  {
    $builder = new DefaultKeyBuilder(prefix: self::PREFIX, withBusinessConnectionId: true);
    $key = $this->makeKey(); // businessConnectionId = null

    self::assertSame(
      self::PREFIX . ':' . self::CHAT_ID . ':' . self::USER_ID,
      $builder->build($key, null),
    );
  }

  /**
   * Custom separator is used between all segments.
   */
  public function testCustomSeparator(): void
  {
    $builder = new DefaultKeyBuilder(prefix: 'myapp', separator: '|', withBotId: true);
    $key = new StorageKey(botId: 10, chatId: 20, userId: 30);

    self::assertSame('myapp|10|20|30', $builder->build($key, null));
  }
}
