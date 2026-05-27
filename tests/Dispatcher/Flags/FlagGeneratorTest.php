<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Dispatcher\Flags;

use Gruven\PhpBotGram\Dispatcher\Flags\Flag;
use Gruven\PhpBotGram\Dispatcher\Flags\FlagGenerator;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class FlagGeneratorTest extends TestCase
{
  public function testMagicCallWithNoArgumentsDefaultsToBooleanTrue(): void
  {
    // Sugar mirror of `aiogram.flags.admin_only` (no parens) — the fluent
    // call site `FlagGenerator::admin_only()` resolves to a marker Flag with
    // `value = true`. We exercise the routing through `__callStatic`
    // explicitly because PHPStan won't synthesise per-name `@method` tags
    // from a `__callStatic` signature; the production call site looks like
    // `FlagGenerator::admin_only()` regardless.
    $flag = FlagGenerator::__callStatic('admin_only', []);

    self::assertInstanceOf(Flag::class, $flag);
    self::assertSame('admin_only', $flag->name);
    self::assertTrue($flag->value);
  }

  public function testMagicCallWithSingleArgumentTransfersAsValue(): void
  {
    // The first positional argument becomes the flag value — `throttle(5)`
    // upstream binds `5` as the value of the `throttle` flag. Mirror that
    // ergonomic.
    $flag = FlagGenerator::__callStatic('throttle', [5]);

    self::assertInstanceOf(Flag::class, $flag);
    self::assertSame('throttle', $flag->name);
    self::assertSame(5, $flag->value);
  }

  public function testMagicCallAcceptsNonScalarValue(): void
  {
    // `chat_action` upstream accepts an `AttrDict({...})` worth of nested
    // settings. Verify the mixed-typed first arg flows through unchanged.
    $opts = ['action' => 'typing', 'interval' => 0.5];

    $flag = FlagGenerator::__callStatic('chat_action', [$opts]);

    self::assertSame('chat_action', $flag->name);
    self::assertSame($opts, $flag->value);
  }

  public function testMagicCallIgnoresTrailingArguments(): void
  {
    // Documented contract: only the first positional arg becomes the value.
    // Extras flow through untouched and get silently dropped — matches
    // upstream's "one value" generator surface.
    $flag = FlagGenerator::__callStatic('throttle', [5, 'extra', 'ignored']);

    self::assertSame('throttle', $flag->name);
    self::assertSame(5, $flag->value);
  }

  public function testFlagHelperReturnsExplicitFlagInstance(): void
  {
    // The non-magic factory `FlagGenerator::flag('x', 1)` exists for
    // statically-typed call sites that would otherwise lose autocompletion
    // through `__callStatic`. Behaviour must match the magic path exactly.
    $flag = FlagGenerator::flag('admin_only');
    self::assertSame('admin_only', $flag->name);
    self::assertTrue($flag->value);

    $valued = FlagGenerator::flag('throttle', 5);
    self::assertSame('throttle', $valued->name);
    self::assertSame(5, $valued->value);
  }

  public function testTwoMagicCallsProduceIndependentFlagInstances(): void
  {
    // Defensive: the generator must not memoize/cache Flag instances —
    // every call returns a fresh DTO so mutation-tracking (if any) of one
    // doesn't bleed into another.
    $a = FlagGenerator::__callStatic('same', []);
    $b = FlagGenerator::__callStatic('same', []);

    self::assertNotSame($a, $b);
    self::assertEquals($a, $b);
  }
}
