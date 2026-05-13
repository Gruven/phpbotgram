<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Filters;

use Exception;
use Gruven\PhpBotGram\Filters\ExceptionTypeFilter;
use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\ErrorEvent;
use Gruven\PhpBotGram\Types\Update;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use TypeError;

/**
 * Coverage for `ExceptionTypeFilter` — port of
 * `aiogram.filters.exception.ExceptionTypeFilter`
 * (`aiogram/filters/exception.py:10-27`).
 *
 * The filter is wired onto the dispatcher's `errors` observer; it accepts
 * an `ErrorEvent` whose `->exception` is an instance of one of the
 * registered class-strings. Mirrors upstream's `isinstance(event.exception,
 * self.exceptions)` check.
 *
 * @internal
 *
 * @coversNothing
 */
final class ExceptionTypeFilterTest extends TestCase
{
  public function testIsAFilterSubclass(): void
  {
    // Smoke-check the inheritance: dispatcher cascading + Logic combinators
    // rely on every concrete filter being a `Filter`. Locking this in
    // protects against accidental refactors that break that contract.
    self::assertInstanceOf(Filter::class, new ExceptionTypeFilter(RuntimeException::class));
  }

  public function testConstructionWithSingleClass(): void
  {
    // Smallest non-empty arity. The constructor stores the registered
    // exceptions as a `list<class-string<Throwable>>` for `instanceof`
    // probing during the call.
    $filter = new ExceptionTypeFilter(RuntimeException::class);

    self::assertSame([RuntimeException::class], $filter->exceptions);
  }

  public function testConstructionWithMultipleClasses(): void
  {
    // Mirrors upstream's `*exceptions: type[Exception]` variadic. The
    // declaration order is preserved on the readonly property so that
    // debug printing and `instanceof` short-circuit are predictable.
    $filter = new ExceptionTypeFilter(
      RuntimeException::class,
      TypeError::class,
      LogicException::class,
    );

    self::assertSame(
      [RuntimeException::class, TypeError::class, LogicException::class],
      $filter->exceptions,
    );
  }

  public function testConstructionWithEmptyArgListThrows(): void
  {
    // Upstream raises `ValueError('At least one exception type is required')`
    // at `aiogram/filters/exception.py:21-23`. PHP equivalent is
    // `InvalidArgumentException` — the closest semantic match in the SPL
    // hierarchy and consistent with `Command`'s empty-list guard.
    $this->expectException(InvalidArgumentException::class);
    new ExceptionTypeFilter();
  }

  public function testAcceptsErrorEventWithExactClassMatch(): void
  {
    // The simplest happy path: the registered class is exactly the
    // raised exception's class. `isinstance` true → return true.
    $filter = new ExceptionTypeFilter(RuntimeException::class);

    self::assertTrue($filter($this->errorEvent(new RuntimeException('boom'))));
  }

  public function testAcceptsErrorEventWithSubclassMatch(): void
  {
    // `instanceof` walks the inheritance chain, so a `RuntimeException`
    // is also a `\Exception` is also a `\Throwable`. Registering the
    // parent class must match any subclass instance — mirroring upstream
    // `isinstance(child, ParentClass) is True`.
    $filter = new ExceptionTypeFilter(Exception::class);

    self::assertTrue($filter($this->errorEvent(new RuntimeException('boom'))));
  }

  public function testAcceptsWhenAnyOneOfMultipleClassesMatches(): void
  {
    // Multi-class registration mirrors upstream's `*exceptions` variadic
    // becoming `isinstance(event.exception, tuple_of_classes)`. The first
    // matching class wins — verify both branches accept.
    $filter = new ExceptionTypeFilter(TypeError::class, RuntimeException::class);

    self::assertTrue($filter($this->errorEvent(new RuntimeException('a'))));
    self::assertTrue($filter($this->errorEvent(new TypeError('b'))));
  }

  public function testRejectsErrorEventWhenNoneOfRegisteredClassesMatch(): void
  {
    // Unrelated exception → no `instanceof` match → reject.
    $filter = new ExceptionTypeFilter(TypeError::class);

    self::assertFalse($filter($this->errorEvent(new RuntimeException('boom'))));
  }

  public function testRejectsNonErrorEventEvents(): void
  {
    // Defensive type guard: a misconfigured router could wire this
    // filter onto a non-errors observer. Reject silently rather than
    // crash. Mirrors `Command::__invoke`'s `if (!$event instanceof Message)`
    // guard so the filter pipeline can't be derailed by the wrong event.
    $filter = new ExceptionTypeFilter(RuntimeException::class);

    self::assertFalse($filter(new Update(updateId: 1)));
    self::assertFalse($filter(new Chat(id: 1, type: 'private')));
  }

  public function testWorksForCustomThrowableSubclasses(): void
  {
    // User code is expected to register custom exception classes (e.g. a
    // domain-specific `BotApiError`). Make sure the filter accepts custom
    // `\Throwable` subclasses, not just SPL exceptions. The class-string
    // contract is `class-string<Throwable>`, which covers any descendant.
    $custom = new class ('boom') extends RuntimeException {};
    $filter = new ExceptionTypeFilter($custom::class);

    self::assertTrue($filter($this->errorEvent($custom)));
  }

  /**
   * Build an `ErrorEvent` carrying an arbitrary `Throwable`. We don't care
   * about the wrapped `Update` shape for these tests — the filter only
   * touches `->exception` — so a bare `Update(updateId: 1)` suffices.
   */
  private function errorEvent(Throwable $e): ErrorEvent
  {
    return new ErrorEvent(new Update(updateId: 1), $e);
  }
}
