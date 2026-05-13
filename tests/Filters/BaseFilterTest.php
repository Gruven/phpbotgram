<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Filters;

use Gruven\PhpBotGram\Filters\BaseFilter;
use Gruven\PhpBotGram\Filters\Filter;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Coverage for `BaseFilter` — the empty-extension alias for `Filter` that
 * mirrors upstream's `aiogram.filters.base.BaseFilter` import path.
 *
 * Upstream user code idiomatically does `from aiogram.filters import BaseFilter`
 * and extends `BaseFilter`. The PHP port exposes the SAME surface so direct
 * ports of user filter classes can use `extends BaseFilter` interchangeably
 * with `extends Filter` — both yield a `Filter` subclass that the dispatcher
 * can consume.
 *
 * @internal
 *
 * @coversNothing
 */
final class BaseFilterTest extends TestCase
{
  public function testBaseFilterIsAbstract(): void
  {
    // Mirrors upstream `Filter(ABC)` — the alias must remain abstract so
    // users are forced to provide a concrete `__invoke` and can't sidestep
    // the contract by instantiating the base directly.
    $reflection = new ReflectionClass(BaseFilter::class);

    self::assertTrue($reflection->isAbstract(), 'BaseFilter must be abstract.');
  }

  public function testBaseFilterExtendsFilter(): void
  {
    // The alias's whole purpose: subclasses written against `BaseFilter`
    // must satisfy `Filter` constraints everywhere the dispatcher narrows.
    // A bare `class BaseFilter extends Filter {}` declaration gives us
    // both `instanceof BaseFilter` AND `instanceof Filter`. We use the
    // reflection API rather than `is_subclass_of` because PHPStan level 9
    // already narrows the latter to a constant `true` (the relationship
    // is statically certain) and refuses the redundant check.
    $reflection = new ReflectionClass(BaseFilter::class);
    $parent = $reflection->getParentClass();

    self::assertNotFalse(
      $parent,
      'BaseFilter must have a parent class.',
    );
    self::assertSame(
      Filter::class,
      $parent->getName(),
      'BaseFilter must directly extend Filter so subclasses are Filter-compatible.',
    );
  }

  public function testConcreteSubclassIsAFilter(): void
  {
    // Round-trip the contract: define an anonymous concrete subclass of
    // BaseFilter and verify the dispatcher-relevant `instanceof Filter`
    // check still passes. This is what makes the alias actually useful
    // for ported user code.
    $concrete = new class extends BaseFilter {
      public function __invoke(object $event, array $kwargs = []): bool
      {
        return true;
      }
    };

    self::assertInstanceOf(BaseFilter::class, $concrete);
    self::assertInstanceOf(Filter::class, $concrete);
  }
}
