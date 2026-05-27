<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Dispatcher\Event;

use Gruven\PhpBotGram\Dispatcher\Event\CallableObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use stdClass;

/**
 * @internal
 */
final class CallableObjectTest extends TestCase
{
  public function testCallableObjectIsNotFinalSoFilterAndHandlerCanExtend(): void
  {
    // `final` was deliberately dropped so `FilterObject` and `HandlerObject`
    // can extend this class, mirroring the upstream `event/handler.py`
    // inheritance shape. If you re-add `final`, the dispatcher's filter
    // pipeline stops compiling.
    self::assertFalse(new ReflectionClass(CallableObject::class)->isFinal());
  }

  public function testParamsExposesDeclaredParameterNamesInOrder(): void
  {
    $callable = new CallableObject(static fn(string $a, int $b): string => $a . $b);

    self::assertSame(['a', 'b'], $callable->params());
    self::assertFalse($callable->isVariadic());
  }

  public function testVariadicOnlyCallbackIsFlaggedAndExposesNoNamedParams(): void
  {
    $callable = new CallableObject(static function (mixed ...$rest): array {
      return $rest;
    });

    self::assertTrue($callable->isVariadic());
    self::assertSame([], $callable->params());
  }

  public function testDeclaredParamsAndVariadicCoexist(): void
  {
    $callable = new CallableObject(static function (string $a, mixed ...$rest): string {
      return $a;
    });

    self::assertSame(['a'], $callable->params());
    self::assertTrue($callable->isVariadic());
  }

  public function testPrepareKwargsFiltersToDeclaredParameters(): void
  {
    $callable = new CallableObject(static fn(string $a, int $b): string => $a);

    self::assertSame(
      ['a' => 1, 'b' => 2],
      $callable->prepareKwargs(['a' => 1, 'b' => 2, 'c' => 3]),
    );
  }

  public function testPrepareKwargsPassesAllArgumentsThroughWhenVariadic(): void
  {
    $callable = new CallableObject(static function (mixed ...$rest): void {});

    self::assertSame(
      ['x' => 1, 'y' => 'two'],
      $callable->prepareKwargs(['x' => 1, 'y' => 'two']),
    );
  }

  public function testPrepareKwargsKeepsDeclaredKeysAndDropsUnknownEvenWithEmptyInput(): void
  {
    $callable = new CallableObject(static fn(string $a, int $b): string => $a);

    self::assertSame([], $callable->prepareKwargs([]));
  }

  public function testCallWithOnlyPositionalArgumentsForwardsThem(): void
  {
    $callable = new CallableObject(static fn(string $a): string => 'got: ' . $a);

    self::assertSame('got: hello', $callable->call(['hello']));
  }

  public function testCallFiltersOutKwargsThatTheClosureDoesNotDeclare(): void
  {
    $callable = new CallableObject(static fn(int $b): int => $b * 2);

    // The `a` kwarg must be dropped — passing it through would raise
    // "unknown named parameter $a" at the call site.
    self::assertSame(10, $callable->call([], ['a' => 99, 'b' => 5]));
  }

  public function testCallMixesPositionalAndFilteredKwargs(): void
  {
    $callable = new CallableObject(static fn(string $a, int $b): string => $a . ':' . $b);

    self::assertSame('x:5', $callable->call(['x'], ['b' => 5, 'c' => 99]));
  }

  public function testCallReturnsWhateverTheClosureReturns(): void
  {
    $nullCallable = new CallableObject(static function (): ?string {
      return null;
    });
    self::assertNull($nullCallable->call());

    $arrayCallable = new CallableObject(static fn(): array => ['x', 'y']);
    self::assertSame(['x', 'y'], $arrayCallable->call());

    $object = new stdClass();
    $object->tag = 'sentinel';
    $objectCallable = new CallableObject(static fn(): stdClass => $object);
    self::assertSame($object, $objectCallable->call());
  }

  public function testCallPropagatesExceptionsWithoutSwallowing(): void
  {
    $callable = new CallableObject(static function (): void {
      throw new RuntimeException('boom');
    });

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('boom');

    $callable->call();
  }

  public function testCallForwardsVariadicKwargsWholeWhenCallbackIsVariadic(): void
  {
    $callable = new CallableObject(static function (mixed ...$rest): array {
      return $rest;
    });

    // For a variadic closure, every kwarg is forwarded — no filtering.
    self::assertSame(
      ['a' => 1, 'b' => 2, 'c' => 3],
      $callable->call([], ['a' => 1, 'b' => 2, 'c' => 3]),
    );
  }

  public function testCallExposesTheCallbackProperty(): void
  {
    $closure = static fn(): int => 7;
    $callable = new CallableObject($closure);

    self::assertSame($closure, $callable->callback);
  }
}
