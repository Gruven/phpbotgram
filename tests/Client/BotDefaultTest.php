<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client;

use Gruven\PhpBotGram\Client\BotDefault;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Upstream: tests/test_api/test_client/test_default.py — class TestDefault
 *
 * Upstream skips:
 *   - test_repr — API divergence (a): Python `repr()` has no PHP equivalent;
 *     PHP's `__debugInfo` / `var_dump` output is not part of the public API.
 *   - test_dataclass_creation_3_10_plus — API divergence (a): Python-specific
 *     `dataclass.__dataclass_params__` introspection, no PHP equivalent.
 *
 * @internal
 */
final class BotDefaultTest extends TestCase
{
  /** Upstream: test_init — stores name internally. */
  public function testStoresName(): void
  {
    $d = new BotDefault('parse_mode');
    self::assertSame('parse_mode', $d->name);
  }

  /** Upstream: test_name_property — name property returns the name. */
  public function testNameProperty(): void
  {
    $d = new BotDefault('test');
    self::assertSame('test', $d->name);
  }

  /** Upstream: test_str — __toString produces human-readable form. */
  public function testToStringIncludesName(): void
  {
    $d = new BotDefault('test');
    self::assertSame("BotDefault('test')", (string)$d);
  }

  /** Upstream: test_eq_same_name + test_eq_different_name via equals(). */
  public function testEqualsByName(): void
  {
    $a = new BotDefault('parse_mode');
    $b = new BotDefault('parse_mode');
    $c = new BotDefault('protect_content');

    self::assertTrue($a->equals($b));
    self::assertFalse($a->equals($c));
    self::assertFalse($a === $b, 'Different instances are not identity-equal');
  }

  /** Upstream: test_hash — equal-name instances produce identical hashes. */
  public function testHashEqualForSameName(): void
  {
    // PHP has no built-in __hash__; the project uses BotDefault::for() as a
    // per-name singleton factory so identity (===) serves as the stable key.
    $a = BotDefault::for('parse_mode');
    $b = BotDefault::for('parse_mode');
    self::assertSame($a, $b, 'BotDefault::for() must return the same instance for the same name');
  }

  public function testJsonSerializeThrows(): void
  {
    $d = new BotDefault('parse_mode');
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('parse_mode');
    json_encode($d, JSON_THROW_ON_ERROR);
  }
}
