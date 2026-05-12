<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Dispatcher\Flags;

use Attribute;
use Gruven\PhpBotGram\Dispatcher\Flags\Flag;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionFunction;

final class FlagTest extends TestCase
{
  public function testConstructDefaultsValueToTrue(): void
  {
    // Upstream `@dataclass(frozen=True)` defaults value to whatever the
    // generator hands in; our generator (FlagGenerator::__callStatic) hands
    // in `true` when the caller omits arguments. Mirror that ergonomic.
    $flag = new Flag('admin_only');

    self::assertSame('admin_only', $flag->name);
    self::assertTrue($flag->value);
  }

  public function testConstructAcceptsMixedValue(): void
  {
    // Values can be ints, strings, arrays, objects — the dispatcher /
    // middleware reads them verbatim. Spot-check a couple representative
    // shapes to make sure the `mixed` type isn't being narrowed.
    $intFlag = new Flag('throttle', 5);
    self::assertSame(5, $intFlag->value);

    $stringFlag = new Flag('chat_action', 'typing');
    self::assertSame('typing', $stringFlag->value);

    $arrayFlag = new Flag('opts', ['a' => 1]);
    self::assertSame(['a' => 1], $arrayFlag->value);
  }

  public function testToStringRendersNameOnlyForTrueValue(): void
  {
    // Boolean-true flags are pure marker flags — `__toString()` renders just
    // the name so debug logs stay grep-friendly (`admin_only` not
    // `admin_only=true`).
    $flag = new Flag('admin_only');

    self::assertSame('admin_only', (string)$flag);
  }

  public function testToStringRendersNameValueForNonTrueValue(): void
  {
    // Non-true scalars render as `name=value` via `var_export()` so quoted
    // strings stay quoted in logs (`chat_action='typing'`).
    $intFlag = new Flag('throttle', 5);
    self::assertSame('throttle=5', (string)$intFlag);

    $stringFlag = new Flag('chat_action', 'typing');
    self::assertSame("chat_action='typing'", (string)$stringFlag);

    $falseFlag = new Flag('disabled', false);
    self::assertSame('disabled=false', (string)$falseFlag);
  }

  public function testFlagIsRepeatableAttributeWithMethodFunctionClassTargets(): void
  {
    // Empirical guard against accidental loss of `IS_REPEATABLE` /
    // TARGET_METHOD / TARGET_FUNCTION / TARGET_CLASS when somebody touches
    // the attribute declaration: read the meta-attribute and assert the
    // exact bitmask. TARGET_CLASS is required so `Flags::extractFlagsFromObject`
    // can read class-level `#[Flag]` decorations.
    $refl = new ReflectionClass(Flag::class);
    $attrs = $refl->getAttributes(Attribute::class);

    self::assertCount(1, $attrs, '#[Attribute] must be declared exactly once on Flag.');

    $attribute = $attrs[0]->newInstance();
    $expected = Attribute::TARGET_METHOD
      | Attribute::TARGET_FUNCTION
      | Attribute::TARGET_CLASS
      | Attribute::IS_REPEATABLE;
    self::assertSame($expected, $attribute->flags);
  }

  public function testFlagAttributesAreCollectedFromClosureViaReflection(): void
  {
    // The headline behaviour: stack multiple `#[Flag(...)]` attributes on a
    // closure literal and verify `ReflectionFunction::getAttributes()` picks
    // them all up. This is the syntactic premise the `Flags` helpers rely on
    // (see FlagsTest).
    $closure = #[Flag('first', 1)] #[Flag('second', 'two')] static fn(): int => 42;

    $refl = new ReflectionFunction($closure);
    $attrs = $refl->getAttributes(Flag::class);

    self::assertCount(2, $attrs);

    $a = $attrs[0]->newInstance();
    self::assertSame('first', $a->name);
    self::assertSame(1, $a->value);

    $b = $attrs[1]->newInstance();
    self::assertSame('second', $b->name);
    self::assertSame('two', $b->value);
  }
}
