<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Filters\F;

use Gruven\PhpBotGram\Filters\F\IntField;
use Gruven\PhpBotGram\Filters\Logic\AndFilter;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilter;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for `IntField` — typed wrapper for integer Telegram fields
 * (`Message::$messageId`, `User::$id`, …). The class narrows comparator
 * signatures so user code can't accidentally pass a non-int and exposes
 * an aggregate `between($lo, $hi)` helper that composes gte+lte under an
 * `AndFilter`.
 */
final class IntFieldTest extends TestCase
{
  public function testEqualsAcceptsExactMatch(): void
  {
    $filter = (new IntField(MagicFilter::root()->id))->equals(42);

    self::assertTrue($filter(new Chat(id: 42, type: 'private')));
    self::assertFalse($filter(new Chat(id: 43, type: 'private')));
  }

  public function testGtRejectsEqualAndLower(): void
  {
    $filter = (new IntField(MagicFilter::root()->id))->gt(10);

    self::assertTrue($filter(new Chat(id: 11, type: 'private')));
    self::assertFalse($filter(new Chat(id: 10, type: 'private')));
    self::assertFalse($filter(new Chat(id: 9, type: 'private')));
  }

  public function testGteAcceptsEqualOrHigher(): void
  {
    $filter = (new IntField(MagicFilter::root()->id))->gte(10);

    self::assertTrue($filter(new Chat(id: 10, type: 'private')));
    self::assertTrue($filter(new Chat(id: 11, type: 'private')));
    self::assertFalse($filter(new Chat(id: 9, type: 'private')));
  }

  public function testLtRejectsEqualAndHigher(): void
  {
    $filter = (new IntField(MagicFilter::root()->id))->lt(10);

    self::assertTrue($filter(new Chat(id: 9, type: 'private')));
    self::assertFalse($filter(new Chat(id: 10, type: 'private')));
    self::assertFalse($filter(new Chat(id: 11, type: 'private')));
  }

  public function testLteAcceptsEqualOrLower(): void
  {
    $filter = (new IntField(MagicFilter::root()->id))->lte(10);

    self::assertTrue($filter(new Chat(id: 10, type: 'private')));
    self::assertTrue($filter(new Chat(id: 9, type: 'private')));
    self::assertFalse($filter(new Chat(id: 11, type: 'private')));
  }

  public function testInAcceptsAnyOfTheGivenValues(): void
  {
    $filter = (new IntField(MagicFilter::root()->id))->in([1, 2, 3]);

    self::assertTrue($filter(new Chat(id: 2, type: 'private')));
    self::assertFalse($filter(new Chat(id: 4, type: 'private')));
  }

  public function testBetweenReturnsAndFilterComposingGteAndLte(): void
  {
    // `between($lo, $hi)` composes gte+lte under an `AndFilter`. We assert
    // both the shape (AndFilter with two targets) and the accept boundary.
    $filter = (new IntField(MagicFilter::root()->id))->between(1, 10);

    self::assertInstanceOf(AndFilter::class, $filter);
    self::assertCount(2, $filter->targets);
  }

  public function testBetweenAcceptsInclusiveBoundsAndRejectsOutside(): void
  {
    // Inclusive boundaries — `between(1, 10)` accepts 1 and 10 but not
    // 0 or 11. Matches the gte+lte pair the implementation composes.
    $filter = (new IntField(MagicFilter::root()->id))->between(1, 10);

    self::assertTrue($filter(new Chat(id: 1, type: 'private')));
    self::assertTrue($filter(new Chat(id: 5, type: 'private')));
    self::assertTrue($filter(new Chat(id: 10, type: 'private')));
    self::assertFalse($filter(new Chat(id: 0, type: 'private')));
    self::assertFalse($filter(new Chat(id: 11, type: 'private')));
  }
}
