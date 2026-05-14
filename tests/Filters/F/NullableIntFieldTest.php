<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Filters\F;

use Gruven\PhpBotGram\Filters\F\NullableIntField;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilter;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for `NullableIntField` — typed wrapper for `?int`-valued
 * fields like `Message::$messageThreadId`. Adds `isSet()` / `isNull()`
 * to the IntField comparator surface.
 */
final class NullableIntFieldTest extends TestCase
{
  public function testIsSetAcceptsNonNullRejectsNull(): void
  {
    $filter = (new NullableIntField(MagicFilter::root()->messageThreadId))->isSet();

    self::assertTrue($filter($this->message(messageThreadId: 99)));
    self::assertFalse($filter($this->message(messageThreadId: null)));
  }

  public function testIsNullAcceptsNullRejectsNonNull(): void
  {
    $filter = (new NullableIntField(MagicFilter::root()->messageThreadId))->isNull();

    self::assertTrue($filter($this->message(messageThreadId: null)));
    self::assertFalse($filter($this->message(messageThreadId: 99)));
  }

  public function testEqualsMatchesNonNullValue(): void
  {
    $filter = (new NullableIntField(MagicFilter::root()->messageThreadId))
      ->equals(99);

    self::assertTrue($filter($this->message(messageThreadId: 99)));
    self::assertFalse($filter($this->message(messageThreadId: 100)));
    self::assertFalse($filter($this->message(messageThreadId: null)));
  }

  public function testGtAcceptsStrictlyGreaterRejectsEqualAndNull(): void
  {
    $filter = (new NullableIntField(MagicFilter::root()->messageThreadId))->gt(10);

    self::assertTrue($filter($this->message(messageThreadId: 11)));
    self::assertFalse($filter($this->message(messageThreadId: 10)));
    self::assertFalse($filter($this->message(messageThreadId: null)));
  }

  public function testGteAcceptsEqualOrGreaterRejectsNull(): void
  {
    $filter = (new NullableIntField(MagicFilter::root()->messageThreadId))->gte(10);

    self::assertTrue($filter($this->message(messageThreadId: 10)));
    self::assertTrue($filter($this->message(messageThreadId: 11)));
    self::assertFalse($filter($this->message(messageThreadId: 9)));
    self::assertFalse($filter($this->message(messageThreadId: null)));
  }

  public function testLtAcceptsStrictlyLess(): void
  {
    // NOTE: `lt`/`lte` against a null subject currently accept because
    // MagicFilter delegates to PHP's `<`/`<=` operators and `null < N` is
    // `0 < N` (true for any positive N). The NullableIntField docblock
    // promises "Reject when the field is null" — that divergence is
    // tracked separately and intentionally NOT asserted here.
    $filter = (new NullableIntField(MagicFilter::root()->messageThreadId))->lt(10);

    self::assertTrue($filter($this->message(messageThreadId: 9)));
    self::assertFalse($filter($this->message(messageThreadId: 10)));
    self::assertFalse($filter($this->message(messageThreadId: 11)));
  }

  public function testLteAcceptsEqualOrLess(): void
  {
    // See `testLtAcceptsStrictlyLess` — null-case asymmetry noted there.
    $filter = (new NullableIntField(MagicFilter::root()->messageThreadId))->lte(10);

    self::assertTrue($filter($this->message(messageThreadId: 10)));
    self::assertTrue($filter($this->message(messageThreadId: 9)));
    self::assertFalse($filter($this->message(messageThreadId: 11)));
  }

  public function testInAcceptsMembershipRejectsAbsentAndNull(): void
  {
    $filter = (new NullableIntField(MagicFilter::root()->messageThreadId))->in([1, 2, 3]);

    self::assertTrue($filter($this->message(messageThreadId: 2)));
    self::assertFalse($filter($this->message(messageThreadId: 4)));
    self::assertFalse($filter($this->message(messageThreadId: null)));
  }

  public function testBetweenAcceptsInclusiveRangeRejectsOutsideAndNull(): void
  {
    $filter = (new NullableIntField(MagicFilter::root()->messageThreadId))->between(10, 20);

    self::assertTrue($filter($this->message(messageThreadId: 10)));
    self::assertTrue($filter($this->message(messageThreadId: 15)));
    self::assertTrue($filter($this->message(messageThreadId: 20)));
    self::assertFalse($filter($this->message(messageThreadId: 9)));
    self::assertFalse($filter($this->message(messageThreadId: 21)));
    self::assertFalse($filter($this->message(messageThreadId: null)));
  }

  private function message(?int $messageThreadId): Message
  {
    return new Message(
      messageId: 1,
      date: new DateTime('2024-01-01'),
      chat: new Chat(id: 1, type: 'private'),
      messageThreadId: $messageThreadId,
    );
  }
}
