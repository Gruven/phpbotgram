<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client;

use DateTimeImmutable;
use DateTimeInterface;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Exceptions\ClientDecodeException;
use Gruven\PhpBotGram\Tests\Support\MockedSession;
use Gruven\PhpBotGram\Types\TelegramObject;
use Gruven\PhpBotGram\Types\Unspecified;
use Gruven\PhpBotGram\Types\User;
use PHPUnit\Framework\TestCase;

final class SerializerTestAliasFixture extends TelegramObject
{
  public const array WireNames = ['fromUser' => 'from'];

  public function __construct(
    public readonly string $fromUser,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}

final class SerializerTestDateFixture extends TelegramObject
{
  public function __construct(
    public readonly DateTimeImmutable $stamp,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}

final class SerializerTestUnspecifiedFixture extends TelegramObject
{
  public function __construct(
    public readonly int $id,
    public readonly bool $isBot,
    public readonly string $firstName,
    public readonly string|Unspecified|null $lastName = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}

final class SerializerTestNestedFixture extends TelegramObject
{
  public function __construct(
    public readonly User $inner,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}

final class SerializerTestArrayFixture extends TelegramObject
{
  /** @param list<User> $users */
  public function __construct(
    public readonly array $users,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}

final class SerializerTestNullableNoDefaultFixture extends TelegramObject
{
  public function __construct(
    public readonly int $id,
    public readonly ?string $tag,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}

/**
 * @internal
 */
final class SerializerTest extends TestCase
{
  public function testDumpStripsUnspecified(): void
  {
    $user = new SerializerTestUnspecifiedFixture(id: 1, isBot: false, firstName: 'A', lastName: Unspecified::instance());
    $dumped = Serializer::dump($user);
    self::assertArrayNotHasKey('last_name', $dumped);
    self::assertSame(1, $dumped['id']);
    self::assertFalse($dumped['is_bot']);
  }

  public function testDumpPreservesNulls(): void
  {
    $user = new User(id: 1, isBot: false, firstName: 'A', lastName: null);
    $dumped = Serializer::dump($user);
    self::assertArrayHasKey('last_name', $dumped);
    self::assertNull($dumped['last_name']);
  }

  public function testLoadConstructsTypeWithBot(): void
  {
    $bot = new Bot(token: '1:test', session: new MockedSession());
    $user = Serializer::load(User::class, ['id' => 5, 'is_bot' => true, 'first_name' => 'B'], $bot);
    self::assertSame(5, $user->id);
    self::assertSame($bot, $user->bot);
  }

  public function testWireNamesAliasRespectedOnDumpAndLoad(): void
  {
    $aliased = new SerializerTestAliasFixture(fromUser: 'alice');
    $dumped = Serializer::dump($aliased);
    self::assertArrayHasKey('from', $dumped);
    self::assertArrayNotHasKey('from_user', $dumped);
    self::assertSame('alice', $dumped['from']);

    $loaded = Serializer::load(SerializerTestAliasFixture::class, ['from' => 'bob']);
    self::assertSame('bob', $loaded->fromUser);
  }

  public function testLoadConvertsIntToPlainDateTimeImmutable(): void
  {
    $loaded = Serializer::load(SerializerTestDateFixture::class, ['stamp' => 1_700_000_000]);
    self::assertSame(1_700_000_000, $loaded->stamp->getTimestamp());
  }

  public function testLoadThrowsClientDecodeOnMissingRequiredKey(): void
  {
    $this->expectException(ClientDecodeException::class);
    Serializer::load(SerializerTestDateFixture::class, []);
  }

  public function testDumpRecursesIntoNestedTelegramObject(): void
  {
    // `dumpValue` walks BotContextController instances recursively so
    // nested objects (`Message::$from`, etc.) flatten into the wire dict.
    $nested = new SerializerTestNestedFixture(
      inner: new User(id: 42, isBot: false, firstName: 'Nested'),
    );

    $dumped = Serializer::dump($nested);

    self::assertIsArray($dumped['inner']);
    self::assertSame(42, $dumped['inner']['id']);
    self::assertSame('Nested', $dumped['inner']['first_name']);
  }

  public function testDumpRecursesIntoListAndAssocArrays(): void
  {
    // Lists and associative arrays both recurse through `dumpValue`. Use a
    // tiny list of users so the array_is_list branch fires and inner
    // objects flatten correctly.
    $payload = new SerializerTestArrayFixture(users: [
      new User(id: 1, isBot: false, firstName: 'A'),
      new User(id: 2, isBot: false, firstName: 'B'),
    ]);

    $dumped = Serializer::dump($payload);

    /** @var list<array<string, mixed>> $users */
    $users = $dumped['users'];
    self::assertIsArray($users);
    self::assertCount(2, $users);
    self::assertSame(1, $users[0]['id']);
    self::assertSame('A', $users[0]['first_name']);
    self::assertSame(2, $users[1]['id']);
  }

  public function testLoadHonoursNullableParamWithoutDefault(): void
  {
    // The "nullable without default" branch in `Serializer::load` fills in
    // an explicit `null` so `newInstance` doesn't ArgumentCountError when
    // the wire payload omits the field.
    $loaded = Serializer::load(SerializerTestNullableNoDefaultFixture::class, ['id' => 7]);

    self::assertSame(7, $loaded->id);
    self::assertNull($loaded->tag);
  }

  public function testLoadRecursesIntoNestedTelegramObject(): void
  {
    // `loadValue` detects a nested TelegramObject param and dispatches a
    // second `load` call. Exercises the `is_subclass_of(TelegramObject)`
    // branch in the named-type path.
    $loaded = Serializer::load(SerializerTestNestedFixture::class, [
      'inner' => ['id' => 9, 'is_bot' => false, 'first_name' => 'NestedLoad'],
    ]);

    self::assertInstanceOf(User::class, $loaded->inner);
    self::assertSame(9, $loaded->inner->id);
    self::assertSame('NestedLoad', $loaded->inner->firstName);
  }

  public function testLoadWrapsTypeErrorInClientDecodeException(): void
  {
    // Wire payload type-mismatch (a non-int for an `int $id` slot) must
    // surface as `ClientDecodeException`, not raw `TypeError`. Mirrors
    // upstream wrapping `pydantic.ValidationError` around `model_validate`.
    $this->expectException(ClientDecodeException::class);
    Serializer::load(SerializerTestNullableNoDefaultFixture::class, [
      'id' => 'not-an-int',
      'tag' => 'ignored',
    ]);
  }

  public function testLoadParsesIsoStringDateForDateTimeImmutable(): void
  {
    // The DateTime fall-through branch accepts ISO-8601 strings so future
    // schema changes (or hand-crafted wire payloads) don't immediately
    // fail. Verify by feeding a known ISO date.
    $loaded = Serializer::load(SerializerTestDateFixture::class, [
      'stamp' => '2024-01-02T03:04:05+00:00',
    ]);

    self::assertSame('2024-01-02T03:04:05+00:00', $loaded->stamp->format(DateTimeInterface::ATOM));
  }
}
