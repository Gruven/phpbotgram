<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm\Storage;

use Error;
use Gruven\PhpBotGram\Fsm\Storage\StorageKey;
use PHPUnit\Framework\TestCase;

/**
 * Upstream `tests/test_fsm/storage/test_key_builder.py` `StorageKey` usage
 * cases deliberately not ported here:
 *
 * - All `StorageKey` construction cases from upstream are ported in this file.
 *   The upstream file exercises `StorageKey` only as a parameter to
 *   `DefaultKeyBuilder.build()`, which is covered in `DefaultKeyBuilderTest`.
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 */
final class StorageKeyTest extends TestCase
{
  public function testConstructionWithRequiredFieldsOnly(): void
  {
    $key = new StorageKey(botId: 1, chatId: 2, userId: 3);

    self::assertSame(1, $key->botId);
    self::assertSame(2, $key->chatId);
    self::assertSame(3, $key->userId);
    self::assertNull($key->threadId);
    self::assertNull($key->businessConnectionId);
    self::assertSame(StorageKey::DEFAULT_DESTINY, $key->destiny);
  }

  public function testConstructionWithAllOptionalFields(): void
  {
    $key = new StorageKey(
      botId: 42,
      chatId: -100,
      userId: 7,
      threadId: 5,
      businessConnectionId: 'bc-xyz',
      destiny: 'my_destiny',
    );

    self::assertSame(42, $key->botId);
    self::assertSame(-100, $key->chatId);
    self::assertSame(7, $key->userId);
    self::assertSame(5, $key->threadId);
    self::assertSame('bc-xyz', $key->businessConnectionId);
    self::assertSame('my_destiny', $key->destiny);
  }

  public function testDefaultDestinyConstantValue(): void
  {
    self::assertSame('default', StorageKey::DEFAULT_DESTINY);
  }

  public function testDefaultDestinyIsApplied(): void
  {
    $key = new StorageKey(botId: 1, chatId: 2, userId: 3);

    self::assertSame('default', $key->destiny);
  }

  public function testReadonlyEnforcementRaisesError(): void
  {
    $key = new StorageKey(botId: 1, chatId: 2, userId: 3);

    $this->expectException(Error::class);

    // @phpstan-ignore-next-line
    $key->botId = 999;
  }

  public function testThreadIdDefaultsToNull(): void
  {
    $key = new StorageKey(botId: 1, chatId: 2, userId: 3);

    self::assertNull($key->threadId);
  }

  public function testBusinessConnectionIdDefaultsToNull(): void
  {
    $key = new StorageKey(botId: 1, chatId: 2, userId: 3);

    self::assertNull($key->businessConnectionId);
  }
}
