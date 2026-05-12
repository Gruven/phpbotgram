<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Dispatcher\Flags;

use Gruven\PhpBotGram\Dispatcher\Flags\Flag;
use Gruven\PhpBotGram\Dispatcher\Flags\FlagDecorator;
use Gruven\PhpBotGram\Dispatcher\Flags\Flags;
use PHPUnit\Framework\TestCase;

#[Flag('class_marker')]
#[Flag('priority', 7)]
final class FlagsTestFixture
{
  public function handler(): bool
  {
    return true;
  }
}

final class FlagsTest extends TestCase
{
  protected function setUp(): void
  {
    FlagDecorator::reset();
  }

  public function testExtractFlagsReturnsEmptyListForBareClosure(): void
  {
    // A closure with no attributes and no imperative attachments has no
    // flags. The contract has to be "empty list", not "empty assoc array" or
    // "null", because callers `foreach` over the result.
    $closure = static fn(): bool => true;

    self::assertSame([], Flags::extractFlags($closure));
  }

  public function testExtractFlagsReturnsImperativeAttachmentsForClosure(): void
  {
    // Pure imperative path: attach a flag via the decorator and verify
    // extractFlags() surfaces it. No attribute reflection involved.
    $closure = static fn(): bool => true;
    $flag = new Flag('admin_only');

    FlagDecorator::attach($closure, $flag);

    self::assertSame([$flag], Flags::extractFlags($closure));
  }

  public function testExtractFlagsReadsAttributeFromClosureLiteral(): void
  {
    // Pure attribute path: the `#[Flag(...)]` decoration on a closure
    // literal must round-trip through ReflectionFunction::getAttributes()
    // and surface in extractFlags().
    $closure = #[Flag('attr_only', 'value')] static fn(): bool => true;

    $flags = Flags::extractFlags($closure);

    self::assertCount(1, $flags);
    self::assertSame('attr_only', $flags[0]->name);
    self::assertSame('value', $flags[0]->value);
  }

  public function testExtractFlagsCombinesImperativeAndAttributeFlags(): void
  {
    // Mixed path: a closure decorated with `#[Flag]` AND given an
    // additional flag via `FlagDecorator::attach()` must expose both.
    // Order: imperative first, then attribute-driven (matches docblock
    // contract for `extractFlags`).
    $closure = #[Flag('via_attribute', 1)] static fn(): bool => true;

    $imperative = new Flag('via_attach', 2);
    FlagDecorator::attach($closure, $imperative);

    $flags = Flags::extractFlags($closure);

    self::assertCount(2, $flags);
    self::assertSame('via_attach', $flags[0]->name);
    self::assertSame(2, $flags[0]->value);
    self::assertSame('via_attribute', $flags[1]->name);
    self::assertSame(1, $flags[1]->value);
  }

  public function testExtractFlagsFromObjectReturnsClassLevelAttributes(): void
  {
    // Class-level `#[Flag]` attributes are the "handler class is itself a
    // flag carrier" path — e.g. an attribute-style command handler class.
    // Verify both flags are surfaced in declaration order.
    $fixture = new FlagsTestFixture();

    $flags = Flags::extractFlagsFromObject($fixture);

    self::assertCount(2, $flags);
    self::assertSame('class_marker', $flags[0]->name);
    self::assertTrue($flags[0]->value);
    self::assertSame('priority', $flags[1]->name);
    self::assertSame(7, $flags[1]->value);
  }

  public function testExtractFlagsOnObjectReadsClassLevelAttributes(): void
  {
    // extractFlags() on an object target must also pick up class-level
    // attributes (not just closures) — same reflection path, different
    // ReflectionClass shape. Cross-check with extractFlagsFromObject().
    $fixture = new FlagsTestFixture();

    $flags = Flags::extractFlags($fixture);

    self::assertCount(2, $flags);
    self::assertSame('class_marker', $flags[0]->name);
    self::assertSame('priority', $flags[1]->name);
  }

  public function testGetFlagReturnsMatchingFlagByName(): void
  {
    // Lookup by name — first match wins. Used by middleware that wants the
    // value of a specific flag (e.g. `getFlag($h, 'chat_action')?->value`).
    $closure = #[Flag('throttle', 5)] static fn(): bool => true;

    $flag = Flags::getFlag($closure, 'throttle');

    self::assertNotNull($flag);
    self::assertSame('throttle', $flag->name);
    self::assertSame(5, $flag->value);
  }

  public function testGetFlagReturnsNullWhenAbsent(): void
  {
    // Absent flag → null. Callers branch on `$flag === null` to apply
    // default behaviour, mirroring upstream's `flags.get(name, default)`.
    $closure = static fn(): bool => true;

    self::assertNull(Flags::getFlag($closure, 'missing'));
  }

  public function testGetFlagPrefersFirstHitWhenMultipleSameName(): void
  {
    // When the same flag name is attached twice (once imperatively, once
    // via attribute), `getFlag` returns the first one in `extractFlags`
    // order — i.e. the imperative attachment. Documenting the precedence
    // explicitly here so middleware authors know which wins.
    $closure = #[Flag('throttle', 999)] static fn(): bool => true;
    FlagDecorator::attach($closure, new Flag('throttle', 1));

    $flag = Flags::getFlag($closure, 'throttle');

    self::assertNotNull($flag);
    self::assertSame(1, $flag->value);
  }

  public function testCheckFlagsReturnsTrueWhenAllRequiredArePresent(): void
  {
    // `checkFlags` is the bulk-presence predicate — every name in the
    // required list must exist on the target. Used by middleware gates
    // (e.g. "only run if `auth` AND `paid` are both set").
    $closure = #[Flag('auth')] #[Flag('paid', 'gold')] static fn(): bool => true;

    self::assertTrue(Flags::checkFlags($closure, ['auth', 'paid']));
  }

  public function testCheckFlagsReturnsFalseWhenAnyRequiredAreMissing(): void
  {
    // One missing flag → false. Combination of imperative + attribute
    // attachments still has to cover the required list completely.
    $closure = #[Flag('auth')] static fn(): bool => true;

    self::assertFalse(Flags::checkFlags($closure, ['auth', 'missing']));
  }

  public function testCheckFlagsReturnsTrueForEmptyRequiredList(): void
  {
    // Vacuous-truth case — an empty required list is satisfied by any
    // target. Documenting so callers can skip a manual `if ($required)`
    // guard.
    $closure = static fn(): bool => true;

    self::assertTrue(Flags::checkFlags($closure, []));
  }

  public function testCheckFlagsMatchesByNameAcrossMixedSources(): void
  {
    // Required: `a` (imperative) and `b` (attribute). Both should satisfy
    // the predicate even though they live in different storages.
    $closure = #[Flag('b')] static fn(): bool => true;
    FlagDecorator::attach($closure, new Flag('a'));

    self::assertTrue(Flags::checkFlags($closure, ['a', 'b']));
  }
}
