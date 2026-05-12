<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Dispatcher\Event;

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
    self::assertSame([$callback], $observer->handlers());
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

    $observer->trigger('a', 'b', 42);

    self::assertSame(['a', 'b', 42], $received);
  }

  public function testTriggerForwardsNamedArguments(): void
  {
    $observer = new EventObserver();
    $received = null;
    $observer->register(static function (string $name) use (&$received): void {
      $received = $name;
    });

    $observer->trigger(name: 'value');

    self::assertSame('value', $received);
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

  public function testHandlersAccessorReturnsTheRegisteredClosuresInOrder(): void
  {
    $observer = new EventObserver();
    $a = static function (): void {};
    $b = static function (): void {};
    $observer->register($a);
    $observer->register($b);

    self::assertSame([$a, $b], $observer->handlers());
  }

  public function testTriggerWithNoHandlersIsANoOp(): void
  {
    $observer = new EventObserver();

    $observer->trigger('anything', key: 'value');

    self::assertSame([], $observer->handlers());
  }
}
