<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Dispatcher\Middlewares;

use Closure;
use Error;
use Gruven\PhpBotGram\Dispatcher\Middlewares\BaseMiddleware;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\TelegramObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class BaseMiddlewareTest extends TestCase
{
  public function testBaseMiddlewareIsAbstractAndCannotBeInstantiated(): void
  {
    $reflection = new ReflectionClass(BaseMiddleware::class);
    self::assertTrue($reflection->isAbstract(), 'BaseMiddleware must be abstract.');

    $this->expectException(Error::class);
    // @phpstan-ignore-next-line — intentional: verify abstract class blocks instantiation.
    $reflection->newInstance();
  }

  public function testConcreteSubclassExecutesAndCallsInnerHandler(): void
  {
    $middleware = new class extends BaseMiddleware {
      public function __invoke(Closure $handler, TelegramObject $event, array $data): mixed
      {
        $data['mw_touched'] = true;

        return $handler($event, $data);
      }
    };

    $event = new Chat(id: 42, type: 'private');
    $captured = null;
    $handler = static function (TelegramObject $e, array $d) use (&$captured): string {
      $captured = ['event' => $e, 'data' => $d];

      return 'handler-result';
    };

    $result = $middleware($handler, $event, ['initial' => 'value']);

    self::assertSame('handler-result', $result);
    self::assertNotNull($captured);
    self::assertSame($event, $captured['event']);
    self::assertSame(['initial' => 'value', 'mw_touched' => true], $captured['data']);
  }
}
