<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Filters;

use Attribute;
use Gruven\PhpBotGram\Filters\CallbackPrefix;
use PHPUnit\Framework\TestCase;
use ReflectionAttribute;
use ReflectionClass;

/**
 * Coverage for the `#[CallbackPrefix]` class attribute. The attribute is the
 * declarative carrier for the prefix/separator pair that `CallbackData`
 * subclasses use to encode/decode callback data payloads. The class itself
 * is intentionally minimal — only public readonly fields plus an `#[Attribute]`
 * marker — so the tests here are correspondingly small: they pin down the
 * attribute target (class only), the default separator, and the override path.
 *
 * Mirrors upstream's `CallbackData.__init_subclass__` keyword arguments
 * (`aiogram/filters/callback_data.py:50-65`) which capture `prefix` and `sep`
 * (default `:`) as class-level metadata.
 *
 * @internal
 */
final class CallbackPrefixTest extends TestCase
{
  public function testConstructionWithDefaultSeparator(): void
  {
    // Default `sep` is `:` — matches upstream `__init_subclass__`'s
    // `cls.__separator__ = kwargs.pop("sep", ":")` default.
    $attr = new CallbackPrefix('my');

    self::assertSame('my', $attr->prefix);
    self::assertSame(':', $attr->sep);
  }

  public function testConstructionWithCustomSeparator(): void
  {
    // Custom separators are supported — e.g. `|` when the data contains
    // colons. Same as upstream `class X(CallbackData, prefix='x', sep='|')`.
    $attr = new CallbackPrefix('order', sep: '|');

    self::assertSame('order', $attr->prefix);
    self::assertSame('|', $attr->sep);
  }

  public function testAttributeIsClassTargeted(): void
  {
    // `CallbackPrefix` is a class-level metadata carrier — never declared on
    // properties or methods. Reading the attribute's own attribute confirms
    // the target is locked to `TARGET_CLASS`. This is what guarantees the
    // `ReflectionClass::getAttributes(CallbackPrefix::class)` lookup inside
    // `CallbackData::reflectMeta` finds it in exactly one place.
    $refl = new ReflectionClass(CallbackPrefix::class);
    $attrs = $refl->getAttributes(Attribute::class);

    self::assertCount(1, $attrs);

    $instance = $attrs[0]->newInstance();
    self::assertInstanceOf(Attribute::class, $instance);
    self::assertSame(Attribute::TARGET_CLASS, $instance->flags);
  }

  public function testIsReadableFromAClassDeclaration(): void
  {
    // End-to-end smoke check: declare a real class with `#[CallbackPrefix]`
    // and recover the instance via reflection — mirroring what
    // `CallbackData::reflectMeta` does internally. Guards against accidental
    // changes that would prevent the attribute from being read by the
    // reflection API the framework relies on.
    $refl = new ReflectionClass(CallbackPrefixTestFixture::class);
    $attrs = $refl->getAttributes(CallbackPrefix::class);

    self::assertCount(1, $attrs);

    $instance = $attrs[0]->newInstance();
    self::assertSame('fixture', $instance->prefix);
    self::assertSame('-', $instance->sep);
  }

  public function testAttributeFlagsAllowSingleInstancePerClass(): void
  {
    // PHP attributes default to `Attribute::TARGET_CLASS` with no
    // `IS_REPEATABLE` flag, which means stacking two
    // `#[CallbackPrefix(...)]` declarations on a single class is a
    // declaration error at instantiation time. We don't assert the runtime
    // error directly (the engine raises during `getAttributes()`), but we
    // do pin down the flag set so a future change to `IS_REPEATABLE`
    // requires updating this test.
    $refl = new ReflectionClass(CallbackPrefix::class);
    $attrs = $refl->getAttributes(Attribute::class, ReflectionAttribute::IS_INSTANCEOF);

    self::assertCount(1, $attrs);

    $instance = $attrs[0]->newInstance();
    self::assertSame(Attribute::TARGET_CLASS, $instance->flags);
  }
}

/**
 * Fixture class for the end-to-end attribute readback test. Kept in the
 * same file as the test to keep the test self-contained — no other code
 * paths reference it.
 *
 * @internal
 */
#[CallbackPrefix('fixture', sep: '-')]
final class CallbackPrefixTestFixture {}
