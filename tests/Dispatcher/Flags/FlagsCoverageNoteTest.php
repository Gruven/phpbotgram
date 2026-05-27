<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Dispatcher\Flags;

use Gruven\PhpBotGram\Dispatcher\Event\HandlerObject;
use Gruven\PhpBotGram\Dispatcher\Flags\Flag;
use Gruven\PhpBotGram\Dispatcher\Flags\FlagDecorator;
use Gruven\PhpBotGram\Dispatcher\Flags\FlagGenerator;
use Gruven\PhpBotGram\Dispatcher\Flags\Flags;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Coverage note for upstream `tests/test_flags/`.
 *
 * **Upstream → local coverage mapping**:
 *
 * | Upstream file             | Disposition                                               |
 * |---------------------------|-----------------------------------------------------------|
 * | test_decorator.py         | Partial API divergence (a): see below                     |
 * | test_getter.py            | Partial API divergence (a): see below                     |
 *
 * ### test_decorator.py
 *
 * Upstream's `FlagDecorator` is an **instance** class: `FlagDecorator(flag)`
 * returns a decorator object whose `__call__` either attaches the flag to a
 * function or chains to create a new `FlagDecorator` with a different value.
 * It supports `__call__(func)` (attach) and `__call__(value)` (rebind value)
 * and `__call__(test=True)` (kwargs mode).
 *
 * phpbotgram's `FlagDecorator` is a **static utility** class: it stores flags
 * in a `WeakMap` keyed by handler identity and exposes `attach()` / `attached()`
 * as static methods. There is no `FlagDecorator(flag)` constructor producing a
 * callable decorator instance, no `_with_value()` method, and no kwargs-mode.
 *
 * All 5 upstream cases in `TestFlagDecorator` fall under **(a) API divergence**.
 * Equivalent PHP functionality is tested in `FlagDecoratorTest`.
 *
 * `TestFlagGenerator::test_getattr` — upstream's `FlagGenerator` is a plain
 * object whose `__getattr__` returns a new `FlagDecorator` per attribute access.
 * phpbotgram's `FlagGenerator` uses `__callStatic` instead; attribute-style
 * access (`$gen->admin_only`) is not the call site. Covered in `FlagGeneratorTest`.
 *
 * `TestFlagGenerator::test_failed_getattr` — upstream raises `AttributeError`
 * for `generator._something` (private prefix). phpbotgram has no such rule
 * because there is no instance `__get`; `FlagGenerator` is a pure static utility.
 * Skip (a) API divergence.
 *
 * ### test_getter.py
 *
 * Upstream's `extract_flags`, `get_flag`, `check_flags` operate on a **data
 * dict** (`{"handler": HandlerObject(..., flags={"key": val}), ...}`). The
 * `flags` key on upstream's `HandlerObject` is a `dict[str, Any]` (not a
 * `list[Flag]`), and `extract_flags(data)` navigates the dict to find the
 * handler's flags dict.
 *
 * phpbotgram's surface is different:
 * - `HandlerObject::$flags` is `array<string, mixed>` (name→value map), NOT a
 *   `list<Flag>`. See `TelegramEventObserverTest::testRegisterStoresFiltersAndFlags`.
 * - `Flags::extractFlags(object $target)` works on the **callable** directly
 *   (reads PHP attributes + WeakMap), not on a data dict.
 * - `Flags::getFlag(object $target, string $name)` returns a `Flag` instance
 *   or `null`, not a raw value.
 * - `Flags::checkFlags(object $target, list<string> $required)` takes a list of
 *   required names, not a MagicFilter.
 *
 * The 9 parametrized `test_get_flag` cases and the 3 `test_check_flag` cases all
 * rely on the data-dict shape and `MagicFilter` integration which are absent
 * in phpbotgram. Skip (a) API divergence.
 *
 * `test_extract_flags_from_object` — equivalent is `FlagsTest::testExtractFlagsFromObjectReturnsClassLevelAttributes`.
 * `test_extract_flags` (data-dict shape) — divergence, skip (a).
 *
 * @internal
 */
final class FlagsCoverageNoteTest extends TestCase
{
  protected function setUp(): void
  {
    FlagDecorator::reset();
  }

  /**
   * Upstream `test_decorator.py::TestFlagDecorator::test_call_with_function`
   * verifies that calling a `FlagDecorator` instance on a function attaches
   * `aiogram_flag` to the function and returns the function unchanged.
   *
   * phpbotgram equivalent: `FlagDecorator::attach($closure, $flag)` attaches
   * via a WeakMap (no attribute mutation) and returns the target unchanged.
   * Already tested in `FlagDecoratorTest`. This test is the cross-reference anchor.
   */
  public function testFlagDecoratorAttachReturnTargetUnchangedMatchesUpstreamCallWithFunction(): void
  {
    // Mirrors upstream test_call_with_function: `decorated is func`
    $closure = static fn(): bool => true;
    $flag = new Flag('test');

    $returned = FlagDecorator::attach($closure, $flag);

    self::assertSame($closure, $returned);
  }

  /**
   * Upstream `test_getter.py::TestGetters::test_extract_flags_from_object`
   * checks that `extract_flags_from_object(func)` returns `{}` when no
   * `aiogram_flag` attribute is set. phpbotgram equivalent: `Flags::extractFlagsFromObject`
   * returns `[]` for an object with no `#[Flag]` attributes.
   *
   * Already tested in `FlagsTest::testExtractFlagsReturnsEmptyListForBareClosure`.
   * Cross-reference anchor here.
   */
  public function testExtractFlagsFromObjectReturnsEmptyListWhenNoFlagsAttached(): void
  {
    // Mirrors upstream test_extract_flags_from_object first branch.
    $obj = new stdClass();

    self::assertSame([], Flags::extractFlagsFromObject($obj));
  }

  /**
   * Upstream `test_getter.py::TestGetters::test_get_flag` has a parametrized
   * case `[{"handler": HandlerObject(lambda: True, flags={"test": True})}, "test", None, True]`
   * that exercises reading a flag from a HandlerObject's flags dict via the
   * data-dict path. phpbotgram's HandlerObject stores flags as `array<string,mixed>`;
   * the equivalent is reading `$handler->flags['test']`.
   */
  public function testHandlerObjectFlagsArrayHoldsRegisteredFlags(): void
  {
    // Ported from test_getter.py::test_get_flag (dict-keyed path).
    // phpbotgram maps upstream `flags={"test": True}` to `$handler->flags = ['test' => true]`.
    // The Flags::getFlag() function works on the *callable*, not HandlerObject;
    // for HandlerObject-level flags, callers read `$handler->flags['name']` directly.
    $handler = new HandlerObject(static fn(): bool => true, [], ['test' => true]);

    self::assertArrayHasKey('test', $handler->flags);
    self::assertTrue($handler->flags['test']);
    self::assertArrayNotHasKey('missing', $handler->flags);
  }

  /**
   * Upstream `test_getter.py::TestGetters::test_get_flag` has a case where
   * `HandlerObject(lambda: True, flags={"test": True})` returns `True` for name
   * `"test"` and the default for `"test2"`. phpbotgram's Flags::getFlag works on
   * the callable — this test uses a closure with an attached flag instead of
   * directly constructing HandlerObject with a dict. Asserts the same semantic.
   */
  public function testFlagsGetFlagReturnsCorrectValueByName(): void
  {
    // Port of test_getter.py parametrized case 7:
    // get_flag({"handler": HandlerObject(lambda: True, flags={"test": True})}, "test", ...) == True
    $closure = #[Flag('test', true)] static fn(): bool => true;

    $flag = Flags::getFlag($closure, 'test');
    self::assertNotNull($flag);
    self::assertTrue($flag->value);

    $missing = Flags::getFlag($closure, 'test2');
    self::assertNull($missing);
  }

  /**
   * Upstream `test_getter.py::TestGetters::test_check_flag` uses MagicFilter
   * (`F.test`) to check flags. phpbotgram has no MagicFilter integration on
   * the flags system — `Flags::checkFlags` takes a list of required names.
   *
   * Skip (a): API divergence — MagicFilter-based flag predicates are absent.
   * Equivalent assertion: `Flags::checkFlags` on a matching name returns `true`.
   */
  public function testFlagsCheckFlagsByNameMatchesEquivalentOfMagicFilterPath(): void
  {
    // test_check_flag: {"test": True}, F.test → True
    // PHP equivalent without MagicFilter integration:
    $closure = #[Flag('test', true)] static fn(): bool => true;

    self::assertTrue(Flags::checkFlags($closure, ['test']));
    self::assertFalse(Flags::checkFlags($closure, ['spam']));
  }

  /**
   * Verifies that `FlagGenerator::__callStatic` creates a `Flag` that can be
   * immediately passed to `FlagDecorator::attach` — the combined workflow from
   * upstream `flags.chat_action(...)` call site.
   *
   * Upstream `TestFlagGenerator::test_getattr` checks `generator.foo is not generator.foo`
   * (each access creates a fresh FlagDecorator instance). phpbotgram has no instance
   * generator — callers use `FlagGenerator::flag('name')` or the magic static form.
   * The freshness invariant is tested in `FlagGeneratorTest::testTwoMagicCallsProduceIndependentFlagInstances`.
   */
  public function testFlagGeneratorProducesUsableFlagForDecoratorAttach(): void
  {
    // Integration: FlagGenerator → FlagDecorator::attach → Flags::getFlag.
    $closure = static fn(): bool => true;
    $generatedFlag = FlagGenerator::flag('chat_action', 'typing');

    FlagDecorator::attach($closure, $generatedFlag);

    $found = Flags::getFlag($closure, 'chat_action');
    self::assertNotNull($found);
    self::assertSame('typing', $found->value);
  }
}
