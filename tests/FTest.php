<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests;

use const Gruven\PhpBotGram\F;

use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilter;
use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilterAsFilter;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Smoke coverage for the namespace-level `Gruven\PhpBotGram\F` constant.
 *
 * The constant is the ergonomic entry-point to the magic-filter DSL —
 * `use const Gruven\PhpBotGram\F;` lets call sites write
 * `F->message->text->equals('hi')` instead of the verbose
 * `MagicFilter::root()->message->text->equals('hi')`.
 *
 * PHP 8.5 added object-initializer support to namespace `const`
 * declarations (the same RFC that allowed `new` in attribute / property
 * default expressions). We verify both the constant exists, the chain
 * builder is immutable from it, and the bridge to `Filter` still works.
 */
final class FTest extends TestCase
{
  public function testFIsRootMagicFilter(): void
  {
    // The const itself must be a `MagicFilter` (not a class symbol, not
    // a closure factory). Without this, `F->something` wouldn't parse
    // as fluent DSL at all.
    self::assertInstanceOf(MagicFilter::class, F);
  }

  public function testFAttributeChainCreatesNewInstances(): void
  {
    // Magic `__get` MUST clone the chain rather than mutate the shared
    // root. Otherwise two independent call sites (`F->message` and
    // `F->text`) would collide on the global `F` constant.
    $a = F->message;
    $b = F->text;

    self::assertNotSame($a, $b);
    self::assertInstanceOf(MagicFilter::class, $a);
    self::assertInstanceOf(MagicFilter::class, $b);
    // And the root must still be untouched — another chain starting
    // from `F` after the two above must again be a fresh instance.
    self::assertNotSame($a, F->message);
  }

  public function testFFluentResolveAgainstNestedObject(): void
  {
    $subject = new stdClass();
    $subject->message = new stdClass();
    $subject->message->text = 'hello';

    $filter = F->message->text->equals('hello');

    self::assertTrue($filter->resolve($subject));
  }

  public function testFFluentResolveAgainstArrayLikeSubject(): void
  {
    // `GetAttributeOperation` falls back to `$subject[$name]` when the
    // subject is an array — `F` chains must work uniformly against
    // both object and associative-array subjects.
    $subject = ['message' => ['text' => 'hi']];

    $filter = F->message->text->equals('hi');

    self::assertTrue($filter->resolve($subject));
  }

  public function testFAsFilterReturnsFilter(): void
  {
    // The end-to-end happy path: `F->…->equals(…)->asFilter()` wraps
    // the chain in a dispatcher-consumable Filter. The concrete bridge
    // type is `MagicFilterAsFilter` but the abstract contract is
    // `Filter` — assert both for clarity.
    $filter = F->message->text->equals('hello')->asFilter();

    self::assertInstanceOf(Filter::class, $filter);
    self::assertInstanceOf(MagicFilterAsFilter::class, $filter);
  }
}
