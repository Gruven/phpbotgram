<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Filters;

use Error;
use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Filters\Logic\AndFilter;
use Gruven\PhpBotGram\Filters\Logic\InvertFilter;
use Gruven\PhpBotGram\Filters\Logic\OrFilter;
use Gruven\PhpBotGram\Types\TelegramObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class FilterTest extends TestCase
{
  public function testFilterIsAbstractAndCannotBeInstantiated(): void
  {
    // Mirror upstream `aiogram.filters.base.Filter` (ABC); the base only
    // exists to be extended by concrete predicates (Command, StateFilter,
    // the Logic combinators below, …).
    $reflection = new ReflectionClass(Filter::class);
    self::assertTrue($reflection->isAbstract(), 'Filter must be abstract.');

    $this->expectException(Error::class);
    // @phpstan-ignore-next-line — intentional: verify abstract class blocks instantiation.
    $reflection->newInstance();
  }

  public function testStaticAllFactoryReturnsAndFilterWrappingAllTargets(): void
  {
    // `Filter::all(...)` is the PHP equivalent of the Python `f1 & f2`
    // operator — composes targets under an AndFilter. Verify the produced
    // combinator preserves both child filters in declaration order.
    $left = new class extends Filter {
      public function __invoke(TelegramObject $event, array $kwargs = []): array|bool
      {
        return true;
      }
    };
    $right = new class extends Filter {
      public function __invoke(TelegramObject $event, array $kwargs = []): array|bool
      {
        return true;
      }
    };

    $combined = Filter::all($left, $right);

    self::assertInstanceOf(AndFilter::class, $combined);
    self::assertSame([$left, $right], $combined->targets);
  }

  public function testStaticAnyFactoryReturnsOrFilterWrappingAllTargets(): void
  {
    // Python equivalent: `f1 | f2`. The first accepting filter's result is
    // forwarded (verified in OrFilterTest); here we only check the factory
    // shape — class, parameter order — to keep this test isolated.
    $left = new class extends Filter {
      public function __invoke(TelegramObject $event, array $kwargs = []): array|bool
      {
        return false;
      }
    };
    $right = new class extends Filter {
      public function __invoke(TelegramObject $event, array $kwargs = []): array|bool
      {
        return true;
      }
    };

    $combined = Filter::any($left, $right);

    self::assertInstanceOf(OrFilter::class, $combined);
    self::assertSame([$left, $right], $combined->targets);
  }

  public function testStaticInvertOfFactoryReturnsInvertFilterWrappingTarget(): void
  {
    // Named `invertOf` (not `not`) because PHP can't co-declare a static
    // helper and a planned instance-side `$filter->not()` under the same
    // name — spec note in the design doc.
    $target = new class extends Filter {
      public function __invoke(TelegramObject $event, array $kwargs = []): array|bool
      {
        return true;
      }
    };

    $inverted = Filter::invertOf($target);

    self::assertInstanceOf(InvertFilter::class, $inverted);
    self::assertSame($target, $inverted->target);
  }
}
