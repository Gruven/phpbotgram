<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Dispatcher\Middlewares;

use Error;
use Gruven\PhpBotGram\Dispatcher\Middlewares\EventContext;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\User;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Verifies the readonly EventContext DTO that `UserContextMiddleware` injects
 * into the dispatcher `$data` bag (see
 * `aiogram/dispatcher/middlewares/user_context.py::EventContext`).
 *
 * @internal
 *
 * @coversNothing
 */
final class EventContextTest extends TestCase
{
  public function testDefaultConstructionAllFieldsNull(): void
  {
    $ctx = new EventContext();

    self::assertNull($ctx->chat);
    self::assertNull($ctx->user);
    self::assertNull($ctx->threadId);
    self::assertNull($ctx->businessConnectionId);
    self::assertNull($ctx->userId());
    self::assertNull($ctx->chatId());
  }

  public function testConstructWithChatAndUserExposesIdsViaAccessors(): void
  {
    $chat = new Chat(id: 42, type: 'private');
    $user = new User(id: 7, isBot: false, firstName: 'Ada');
    $ctx = new EventContext(chat: $chat, user: $user, threadId: 13, businessConnectionId: 'bc-1');

    self::assertSame($chat, $ctx->chat);
    self::assertSame($user, $ctx->user);
    self::assertSame(13, $ctx->threadId);
    self::assertSame('bc-1', $ctx->businessConnectionId);
    self::assertSame(7, $ctx->userId());
    self::assertSame(42, $ctx->chatId());
  }

  public function testUserIdIsNullWhenUserAbsent(): void
  {
    $ctx = new EventContext(chat: new Chat(id: 1, type: 'private'));

    self::assertNull($ctx->userId());
    self::assertSame(1, $ctx->chatId());
  }

  public function testChatIdIsNullWhenChatAbsent(): void
  {
    $ctx = new EventContext(user: new User(id: 99, isBot: true, firstName: 'Bot'));

    self::assertSame(99, $ctx->userId());
    self::assertNull($ctx->chatId());
  }

  public function testClassIsReadonlyAndAssignmentThrows(): void
  {
    $reflection = new ReflectionClass(EventContext::class);
    self::assertTrue($reflection->isReadOnly(), 'EventContext must be a readonly class.');

    $ctx = new EventContext();
    $this->expectException(Error::class);
    // @phpstan-ignore-next-line — intentional: verify readonly blocks mutation at runtime.
    $ctx->threadId = 42;
  }
}
