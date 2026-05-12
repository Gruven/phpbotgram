<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client\Session\Middleware;

use Closure;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Session\Middleware\BaseRequestMiddleware;
use Gruven\PhpBotGram\Client\Session\Middleware\RequestMiddlewareManager;
use Gruven\PhpBotGram\Methods\TelegramMethod;
use Gruven\PhpBotGram\Tests\Support\MockedSession;
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
}
