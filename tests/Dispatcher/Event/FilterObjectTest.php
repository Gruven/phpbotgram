<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Dispatcher\Event;

use Gruven\PhpBotGram\Dispatcher\Event\CallableObject;
use Gruven\PhpBotGram\Dispatcher\Event\FilterObject;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class FilterObjectTest extends TestCase
{
  public function testFilterObjectIsACallableObject(): void
  {
    // Upstream models FilterObject as a CallableObject subclass — keeping
    // that inheritance lets HandlerObject treat each filter uniformly via
    // CallableObject::call() and inherit prepareKwargs() semantics for free.
    $filter = new FilterObject(static fn(): bool => true);

    self::assertInstanceOf(CallableObject::class, $filter);
  }

  public function testFilterInheritsPrepareKwargsFromCallableObject(): void
  {
    // Filter accepting only `$a` should drop the irrelevant `$b` kwarg the
    // dispatcher injects, mirroring how a handler signature self-selects
    // context. Capture what the closure actually received to verify the
    // kwarg-filtering plumbing came through inheritance.
    $received = null;
    $filter = new FilterObject(static function (string $a) use (&$received): bool {
      $received = $a;

      return true;
    });

    $filter->call([], ['a' => 'kept', 'b' => 'dropped']);

    self::assertSame('kept', $received);
  }

  public function testFilterCallForwardsReturnValueUnchanged(): void
  {
    // FilterObject is a thin marker over CallableObject in Phase 3 — the
    // result-interpretation logic lives in HandlerObject::check(). Verify
    // call() simply returns whatever the underlying closure returns so the
    // caller can branch on `false` / `true` / `array`.
    $trueFilter = new FilterObject(static fn(): bool => true);
    self::assertTrue($trueFilter->call());

    $falseFilter = new FilterObject(static fn(): bool => false);
    self::assertFalse($falseFilter->call());

    $arrayFilter = new FilterObject(static fn(): array => ['inject' => 'value']);
    self::assertSame(['inject' => 'value'], $arrayFilter->call());
  }

  public function testFilterParamsAndVariadicReportThroughInheritance(): void
  {
    // Sanity: the reflection-cached params() / isVariadic() introspection
    // surfaces through the subclass without re-declaration.
    $filter = new FilterObject(static fn(string $a, int $b): bool => true);

    self::assertSame(['a', 'b'], $filter->params());
    self::assertFalse($filter->isVariadic());

    $variadicFilter = new FilterObject(static fn(mixed ...$rest): bool => true);
    self::assertTrue($variadicFilter->isVariadic());
  }
}
