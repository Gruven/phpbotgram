<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client\Session\Middleware;

use Closure;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Session\Middleware\BaseRequestMiddleware;
use Gruven\PhpBotGram\Client\Session\Middleware\RequestMiddlewareManager;
use Gruven\PhpBotGram\Methods\SendMessage;
use Gruven\PhpBotGram\Methods\TelegramMethod;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Tests\Support\MockedSession;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\Message;
use PHPUnit\Framework\TestCase;
use stdClass;

final class RequestMiddlewareManagerTest extends TestCase
{
  public function testWrapWithoutMiddlewareReturnsTerminal(): void
  {
    $manager = new RequestMiddlewareManager();
    $terminal = static fn(int $n): int => $n * 2;
    $wrapped = $manager->wrap($terminal);
    self::assertSame(10, $wrapped(5));
  }

  public function testWrapChainsInRegistrationOrder(): void
  {
    $manager = new RequestMiddlewareManager();
    $log = [];

    $manager->register(new class ($log) extends BaseRequestMiddleware {
      /** @param list<string> $log */
      public function __construct(public array &$log) {}
      public function __invoke(Closure $next, Bot $bot, TelegramMethod $method, ?int $timeout = null): mixed
      {
        $this->log[] = 'A-before';
        $result = $next($bot, $method, $timeout);
        $this->log[] = 'A-after';

        return $result;
      }
    });

    $manager->register(new class ($log) extends BaseRequestMiddleware {
      /** @param list<string> $log */
      public function __construct(public array &$log) {}
      public function __invoke(Closure $next, Bot $bot, TelegramMethod $method, ?int $timeout = null): mixed
      {
        $this->log[] = 'B-before';
        $result = $next($bot, $method, $timeout);
        $this->log[] = 'B-after';

        return $result;
      }
    });

    $terminal = static function (...$args) use (&$log) {
      $log[] = 'terminal';

      return 'done';
    };

    $wrapped = $manager->wrap($terminal);
    $result = $wrapped(
      new Bot(token: '1:test', session: new MockedSession()),
      new class extends TelegramMethod {
        public const string ApiMethod = 'x';
        public const string ReturnsType = stdClass::class;
      },
    );

    self::assertSame('done', $result);
    self::assertSame(['A-before', 'B-before', 'terminal', 'B-after', 'A-after'], $log);
  }

  public function testUnregisterRemovesMiddleware(): void
  {
    $manager = new RequestMiddlewareManager();
    $mw = new class extends BaseRequestMiddleware {
      public function __invoke(Closure $next, Bot $bot, TelegramMethod $method, ?int $timeout = null): mixed
      {
        return $next($bot, $method, $timeout);
      }
    };
    $manager->register($mw);
    self::assertCount(1, $manager);
    self::assertTrue($manager->unregister($mw));
    self::assertCount(0, $manager);
    self::assertFalse($manager->unregister($mw), 'unregister of an absent middleware returns false');
  }

  public function testInvokeAsDecoratorFactory(): void
  {
    $manager = new RequestMiddlewareManager();
    $mw = new class extends BaseRequestMiddleware {
      public function __invoke(Closure $next, Bot $bot, TelegramMethod $method, ?int $timeout = null): mixed
      {
        return $next($bot, $method, $timeout);
      }
    };
    // Inline registration
    $returned = $manager($mw);
    self::assertSame($mw, $returned);
    self::assertCount(1, $manager);
    // Decorator factory (no arg)
    $registrar = $manager();
    self::assertInstanceOf(Closure::class, $registrar);
  }

  public function testArrayAccessReadsRegisteredMiddleware(): void
  {
    $manager = new RequestMiddlewareManager();
    $mw = new class extends BaseRequestMiddleware {
      public function __invoke(Closure $next, Bot $bot, TelegramMethod $method, ?int $timeout = null): mixed
      {
        return $next($bot, $method, $timeout);
      }
    };
    $manager->register($mw);
    self::assertTrue(isset($manager[0]));
    self::assertSame($mw, $manager[0]);
    self::assertFalse(isset($manager[1]));
  }

  public function testBaseSessionInvokeRunsMiddlewareChain(): void
  {
    $bot = new MockedBot();
    $session = $bot->getMockedSession();
    $log = [];

    $session->middleware->register(new class ($log) extends BaseRequestMiddleware {
      /** @param list<string> $log */
      public function __construct(public array &$log) {}

      public function __invoke(Closure $next, Bot $bot, TelegramMethod $method, ?int $timeout = null): mixed
      {
        $this->log[] = 'mw-before';
        $result = $next($bot, $method, $timeout);
        $this->log[] = 'mw-after';

        return $result;
      }
    });

    $payload = new Message(
      messageId: 1,
      messageThreadId: null,
      directMessagesTopic: null,
      fromUser: null,
      senderChat: null,
      senderBoostCount: null,
      senderBusinessBot: null,
      senderTag: null,
      date: new DateTime('@0'),
      guestQueryId: null,
      businessConnectionId: null,
      chat: new Chat(id: 1, type: 'private'),
    );
    $bot->addResultFor(SendMessage::class, ok: true, result: $payload);

    $method = new SendMessage(chatId: 1, text: 'hi');
    $returned = $session($bot, $method);

    self::assertSame($payload, $returned);
    self::assertSame(['mw-before', 'mw-after'], $log);
  }
}
