<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\MagicFilter;

use Gruven\PhpBotGram\Utils\MagicFilter\AttrDict;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for `AttrDict` — the hybrid object/array used by the
 * `MagicData` filter (to wrap middleware data dicts so MagicFilter
 * rules can walk them with either `F->foo` or `F['foo']`).
 *
 * @internal
 */
final class AttrDictTest extends TestCase
{
  public function testReadsViaPropertyAccess(): void
  {
    // Object-style read goes through `__get`.
    $dict = new AttrDict(['name' => 'aiogram']);

    self::assertSame('aiogram', $dict->name);
  }

  public function testReadsViaSubscriptAccess(): void
  {
    // Array-style read goes through `offsetGet`.
    $dict = new AttrDict(['name' => 'aiogram']);

    self::assertSame('aiogram', $dict['name']);
  }

  public function testWriteThroughPropertyAndSubscriptStayInSync(): void
  {
    // Both pathways mutate the same underlying map; reading via the
    // other interface returns the latest value.
    $dict = new AttrDict();
    $dict->one = 1;
    $dict['two'] = 2;

    self::assertSame(1, $dict['one']);
    self::assertSame(2, $dict->two);
  }

  public function testIsCountable(): void
  {
    $dict = new AttrDict(['a' => 1, 'b' => 2, 'c' => 3]);

    self::assertCount(3, $dict);
  }

  public function testIsIterable(): void
  {
    $dict = new AttrDict(['a' => 1, 'b' => 2]);
    $copy = [];

    foreach ($dict as $key => $value) {
      $copy[$key] = $value;
    }

    self::assertSame(['a' => 1, 'b' => 2], $copy);
  }

  public function testToArrayReturnsBackingMap(): void
  {
    $dict = new AttrDict(['x' => 'y']);

    self::assertSame(['x' => 'y'], $dict->toArray());
  }

  public function testIssetAndUnset(): void
  {
    $dict = new AttrDict(['x' => 1]);

    self::assertTrue(isset($dict->x));
    unset($dict->x);
    self::assertFalse(isset($dict->x));
  }

  public function testMissingKeyReturnsNullRatherThanWarning(): void
  {
    // We intentionally swallow the missing-key case so the MagicFilter
    // resolver can fall back to its rejection path; surfacing a PHP
    // warning would corrupt the resolver's flow.
    $dict = new AttrDict([]);

    self::assertNull($dict->absent);
    self::assertNull($dict['absent']);
  }
}
