<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Dispatcher\Event;

use Gruven\PhpBotGram\Dispatcher\Event\CallableObject;
use Gruven\PhpBotGram\Dispatcher\Event\EventObserver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EventObserverTest extends TestCase
{
  public function testRegisterReturnsTheCallbackUnchanged(): void
  {
    $observer = new EventObserver();
    $callback = static function (): void {};

    self::assertSame($callback, $observer->register($callback));
  }

  public function testRegisterWrapsTheCallbackInACallableObject(): void
  {
    $observer = new EventObserver();
    $callback = static function (): void {};
    $observer->register($callback);

    $handlers = $observer->handlers();
    self::assertCount(1, $handlers);
    self::assertInstanceOf(CallableObject::class, $handlers[0]);
    self::assertSame($callback, $handlers[0]->callback);
  }

  public function testTriggerInvokesHandlersInRegistrationOrder(): void
  {
    $observer = new EventObserver();
    $log = [];
    $observer->register(static function () use (&$log): void {
      $log[] = 'first';
    });
    $observer->register(static function () use (&$log): void {
      $log[] = 'second';
    });
    $observer->register(static function () use (&$log): void {
      $log[] = 'third';
    });

    $observer->trigger();

    self::assertSame(['first', 'second', 'third'], $log);
  }

  public function testTriggerForwardsPositionalArguments(): void
  {
    $observer = new EventObserver();
    $received = [];
    $observer->register(static function (mixed ...$args) use (&$received): void {
      $received = $args;
    });

    $observer->trigger(['a', 'b', 42]);

    self::assertSame(['a', 'b', 42], $received);
  }

  public function testTriggerForwardsNamedArguments(): void
  {
    $observer = new EventObserver();
    $received = null;
    $observer->register(static function (string $name) use (&$received): void {
      $received = $name;
    });

    $observer->trigger([], ['name' => 'value']);

    self::assertSame('value', $received);
  }

  public function testTriggerFiltersKwargsByEachHandlerDeclaredParameters(): void
  {
    $observer = new EventObserver();
    $captured = ['a' => null, 'has_b' => null];

    // This handler declares only $a — the dispatcher-style "$b" kwarg
    // must be silently dropped by CallableObject, not forwarded.
    $observer->register(static function (string $a) use (&$captured): void {
      $captured['a'] = $a;
      $captured['has_b'] = func_num_args() === 2;
    });

    $observer->trigger([], ['a' => 'hello', 'b' => 'wrong']);

    self::assertSame('hello', $captured['a']);
    self::assertFalse($captured['has_b'], 'Undeclared `b` kwarg must be dropped, not forwarded.');
  }

  public function testTriggerKwargsAreFilteredPerHandlerIndependently(): void
  {
    $observer = new EventObserver();
    $observedA = null;
    $observedB = null;
    $observer->register(static function (string $a) use (&$observedA): void {
      $observedA = $a;
    });
    $observer->register(static function (string $b) use (&$observedB): void {
      $observedB = $b;
    });

    $observer->trigger([], ['a' => 'alpha', 'b' => 'beta', 'unused' => 'noise']);

    self::assertSame('alpha', $observedA);
    self::assertSame('beta', $observedB);
  }

  public function testClearEmptiesHandlersAndTriggerIsANoOp(): void
  {
    $observer = new EventObserver();
    $called = false;
    $observer->register(static function () use (&$called): void {
      $called = true;
    });

    $observer->clear();

    self::assertSame([], $observer->handlers());

    $observer->trigger();

    self::assertFalse($called);
  }

  public function testMultipleHandlersAllFireOnASingleTrigger(): void
  {
    $observer = new EventObserver();
    $counter = 0;
    $observer->register(static function () use (&$counter): void {
      ++$counter;
    });
    $observer->register(static function () use (&$counter): void {
      ++$counter;
    });
    $observer->register(static function () use (&$counter): void {
      ++$counter;
    });

    $observer->trigger();

    self::assertSame(3, $counter);
  }

  public function testHandlerExceptionsPropagateAndAbortLaterHandlers(): void
  {
    $observer = new EventObserver();
    $afterCalled = false;
    $observer->register(static function (): void {
      throw new RuntimeException('boom');
    });
    $observer->register(static function () use (&$afterCalled): void {
      $afterCalled = true;
    });

    try {
      $observer->trigger();
      self::fail('Expected RuntimeException to propagate from the handler.');
    } catch (RuntimeException $e) {
      self::assertSame('boom', $e->getMessage());
    }

    self::assertFalse(
      $afterCalled,
      'Handlers registered after the throwing handler must not run when the observer does not catch exceptions.',
    );
  }

  public function testHandlersAccessorReturnsTheRegisteredCallableObjectsInOrder(): void
  {
    $observer = new EventObserver();
    $a = static function (): void {};
    $b = static function (): void {};
    $observer->register($a);
    $observer->register($b);

    $handlers = $observer->handlers();
    self::assertCount(2, $handlers);
    self::assertSame($a, $handlers[0]->callback);
    self::assertSame($b, $handlers[1]->callback);
  }

  public function testTriggerWithNoHandlersIsANoOp(): void
  {
    $observer = new EventObserver();

    $observer->trigger(['anything'], ['key' => 'value']);

    self::assertSame([], $observer->handlers());
  }
}
