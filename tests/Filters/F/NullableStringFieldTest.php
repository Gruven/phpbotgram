<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Filters\F;

use Gruven\PhpBotGram\Filters\F\NullableStringField;
use Gruven\PhpBotGram\Types\User;
use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilter;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for `NullableStringField` — typed wrapper for the
 * `?string`-valued Telegram fields (`User::$lastName`, `User::$username`,
 * `Message::$text`, …). Adds `isSet()` / `isNull()` to the StringField
 * surface so callers can express null-checks without dropping into raw
 * MagicFilter.
 */
final class NullableStringFieldTest extends TestCase
{
  public function testIsSetAcceptsNonNullRejectsNull(): void
  {
    // `isSet()` accepts a present value (regardless of content) and
    // rejects a null. The implementation reads as `notEquals(null)`.
    $filter = (new NullableStringField(MagicFilter::root()->lastName))->isSet();

    self::assertTrue($filter($this->user(lastName: 'Doe')));
    self::assertFalse($filter($this->user(lastName: null)));
  }

  public function testIsNullAcceptsNullRejectsNonNull(): void
  {
    $filter = (new NullableStringField(MagicFilter::root()->lastName))->isNull();

    self::assertTrue($filter($this->user(lastName: null)));
    self::assertFalse($filter($this->user(lastName: 'Doe')));
  }

  public function testEqualsMatchesNonNullValue(): void
  {
    // For `equals($value)` with a non-null subject the typed wrapper
    // delegates to MagicFilter's `==` comparator — same semantics as
    // StringField, just with a nullable subject. Null rejects.
    $filter = (new NullableStringField(MagicFilter::root()->lastName))
      ->equals('Doe');

    self::assertTrue($filter($this->user(lastName: 'Doe')));
    self::assertFalse($filter($this->user(lastName: 'Smith')));
    self::assertFalse($filter($this->user(lastName: null)));
  }

  public function testIsSetReturnsFreshFilterPerCall(): void
  {
    // Defensive: ensure repeated calls produce independent Filter
    // instances — the chain immutability guarantee must propagate
    // through the typed wrapper.
    $field = new NullableStringField(MagicFilter::root()->lastName);
    $a = $field->isSet();
    $b = $field->isSet();

    self::assertNotSame($a, $b);
  }

  public function testContainsMatchesSubstringRejectsNull(): void
  {
    $filter = (new NullableStringField(MagicFilter::root()->lastName))->contains('oe');

    self::assertTrue($filter($this->user(lastName: 'Doe')));
    self::assertFalse($filter($this->user(lastName: 'Smith')));
    self::assertFalse($filter($this->user(lastName: null)));
  }

  public function testStartsWithMatchesPrefixRejectsNull(): void
  {
    $filter = (new NullableStringField(MagicFilter::root()->lastName))->startsWith('D');

    self::assertTrue($filter($this->user(lastName: 'Doe')));
    self::assertFalse($filter($this->user(lastName: 'Smith')));
    self::assertFalse($filter($this->user(lastName: null)));
  }

  public function testEndsWithMatchesSuffixRejectsNull(): void
  {
    $filter = (new NullableStringField(MagicFilter::root()->lastName))->endsWith('oe');

    self::assertTrue($filter($this->user(lastName: 'Doe')));
    self::assertFalse($filter($this->user(lastName: 'Smith')));
    self::assertFalse($filter($this->user(lastName: null)));
  }

  public function testInAcceptsMembershipRejectsAbsentAndNull(): void
  {
    $filter = (new NullableStringField(MagicFilter::root()->lastName))
      ->in(['Doe', 'Roe']);

    self::assertTrue($filter($this->user(lastName: 'Doe')));
    self::assertFalse($filter($this->user(lastName: 'Smith')));
    self::assertFalse($filter($this->user(lastName: null)));
  }

  private function user(?string $lastName): User
  {
    return new User(id: 1, isBot: false, firstName: 'Alice', lastName: $lastName);
  }
}
