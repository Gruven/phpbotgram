<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Dispatcher\Middlewares;

use Closure;
use Gruven\PhpBotGram\Dispatcher\Middlewares\BaseMiddleware;
use Gruven\PhpBotGram\Dispatcher\Middlewares\MiddlewareManager;
use Gruven\PhpBotGram\Types\Chat;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

/**
 * @internal
 */
final class MiddlewareManagerTest extends TestCase
{
  public function testEmptyManagerCountAndWrapReturnsTerminal(): void
  {
    $manager = new MiddlewareManager();
    self::assertCount(0, $manager);

    $terminal = static fn(object $e, array $d): string => 'terminal';
    self::assertSame($terminal, $manager->wrap($terminal));
  }

  public function testRegisterAddsMiddlewareAndExposesViaArrayAccess(): void
  {
    $manager = new MiddlewareManager();
    $mw = self::passthroughMiddleware();

    self::assertSame($mw, $manager->register($mw));
    self::assertCount(1, $manager);
    self::assertTrue(isset($manager[0]));
    self::assertSame($mw, $manager[0]);
    self::assertFalse(isset($manager[1]));

    $terminal = static fn(object $e, array $d): string => 'terminal';
    self::assertNotSame($terminal, $manager->wrap($terminal));
  }

  public function testWrapCallsMiddlewareBeforeAndAfterTerminal(): void
  {
    $manager = new MiddlewareManager();
    $log = [];

    $manager->register(new class ($log) extends BaseMiddleware {
      /** @param list<string> $log */
      public function __construct(public array &$log) {}

      public function __invoke(Closure $handler, object $event, array $data): mixed
      {
        $this->log[] = 'before';
        $result = $handler($event, $data);
        $this->log[] = 'after';

        return $result;
      }
    });

    $terminal = static function (object $e, array $d) use (&$log): string {
      $log[] = 'terminal';

      return 'done';
    };

    $wrapped = $manager->wrap($terminal);
    $result = $wrapped(new Chat(id: 1, type: 'private'), []);

    self::assertSame('done', $result);
    self::assertSame(['before', 'terminal', 'after'], $log);
  }

  public function testMultipleMiddlewaresWrapInRegistrationOrder(): void
  {
    $manager = new MiddlewareManager();
    $log = [];

    $manager->register(self::loggingMiddleware('A', $log));
    $manager->register(self::loggingMiddleware('B', $log));
    $manager->register(self::loggingMiddleware('C', $log));

    $terminal = static function (object $e, array $d) use (&$log): string {
      $log[] = 'terminal';

      return 'done';
    };

    $wrapped = $manager->wrap($terminal);
    $result = $wrapped(new Chat(id: 1, type: 'private'), []);

    self::assertSame('done', $result);
    self::assertSame(
      ['A-before', 'B-before', 'C-before', 'terminal', 'C-after', 'B-after', 'A-after'],
      $log,
    );
  }

  public function testUnregisterRemovesMiddlewareAndReturnsBoolStatus(): void
  {
    $manager = new MiddlewareManager();
    $mw = self::passthroughMiddleware();

    $manager->register($mw);
    self::assertCount(1, $manager);

    self::assertTrue($manager->unregister($mw));
    self::assertCount(0, $manager);

    self::assertFalse(
      $manager->unregister($mw),
      'Unregister of an absent middleware must return false.',
    );
  }

  public function testInvokeAsDecoratorFactoryInlineAndReturningClosure(): void
  {
    $manager = new MiddlewareManager();
    $mw = self::passthroughMiddleware();

    // Inline form: $manager($mw) registers and returns the middleware.
    $returned = $manager($mw);
    self::assertSame($mw, $returned);
    self::assertCount(1, $manager);

    // Factory form: $manager() returns a registration Closure.
    $registrar = $manager();
    self::assertInstanceOf(Closure::class, $registrar);

    $mw2 = self::passthroughMiddleware();
    $registered = $registrar($mw2);
    self::assertSame($mw2, $registered);
    self::assertCount(2, $manager);
    self::assertSame($mw2, $manager[1]);
  }

  public function testOffsetGetOutOfBoundsThrows(): void
  {
    $manager = new MiddlewareManager();

    $this->expectException(OutOfBoundsException::class);
    // @phpstan-ignore-next-line — exercising OOB read on purpose.
    $manager[5];
  }

  public function testOffsetSetThrowsBecauseManagerIsAppendOnly(): void
  {
    $manager = new MiddlewareManager();
    $mw = self::passthroughMiddleware();

    $this->expectException(RuntimeException::class);
    $manager[0] = $mw;
  }

  public function testOffsetUnsetThrowsBecauseManagerIsAppendOnly(): void
  {
    $manager = new MiddlewareManager();
    $manager->register(self::passthroughMiddleware());

    $this->expectException(RuntimeException::class);
    unset($manager[0]);
  }

  public function testMiddlewareCanMutateDataBeforeHandler(): void
  {
    $manager = new MiddlewareManager();

    $manager->register(new class extends BaseMiddleware {
      public function __invoke(Closure $handler, object $event, array $data): mixed
      {
        $data['mw_key'] = 'mw_value';

        return $handler($event, $data);
      }
    });

    $observed = null;
    $terminal = static function (object $e, array $d) use (&$observed): array {
      $observed = $d;

      return $d;
    };

    $wrapped = $manager->wrap($terminal);
    $wrapped(new Chat(id: 1, type: 'private'), ['caller_key' => 'caller_value']);

    self::assertSame(
      ['caller_key' => 'caller_value', 'mw_key' => 'mw_value'],
      $observed,
    );
  }

  public function testMiddlewareCanShortCircuitReturnValueWithoutCallingHandler(): void
  {
    $manager = new MiddlewareManager();
    $sentinel = new stdClass();

    $handlerCalled = false;
    $manager->register(new class ($sentinel) extends BaseMiddleware {
      public function __construct(public stdClass $sentinel) {}

      public function __invoke(Closure $handler, object $event, array $data): mixed
      {
        return $this->sentinel;
      }
    });

    $terminal = static function (object $e, array $d) use (&$handlerCalled): string {
      $handlerCalled = true;

      return 'should-not-be-reached';
    };

    $wrapped = $manager->wrap($terminal);
    $result = $wrapped(new Chat(id: 1, type: 'private'), []);

    self::assertSame($sentinel, $result);
    self::assertFalse($handlerCalled, 'Short-circuiting middleware must not invoke the inner handler.');
  }

  private static function passthroughMiddleware(): BaseMiddleware
  {
    return new class extends BaseMiddleware {
      public function __invoke(Closure $handler, object $event, array $data): mixed
      {
        return $handler($event, $data);
      }
    };
  }

  /**
   * @param list<string> $log
   */
  private static function loggingMiddleware(string $name, array &$log): BaseMiddleware
  {
    return new class ($name, $log) extends BaseMiddleware {
      /** @param list<string> $log */
      public function __construct(public string $name, public array &$log) {}

      public function __invoke(Closure $handler, object $event, array $data): mixed
      {
        $this->log[] = $this->name . '-before';
        $result = $handler($event, $data);
        $this->log[] = $this->name . '-after';

        return $result;
      }
    };
  }
}
