<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Filters\F;

use Gruven\PhpBotGram\Filters\F\IntField;
use Gruven\PhpBotGram\Filters\F\StringField;
use Gruven\PhpBotGram\Types\User;
use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilter;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for `StringField` — the typed wrapper for string-valued
 * Telegram fields (`Message::$text`, `User::$firstName`, …). Each method
 * either returns a terminal `Filter` for direct dispatcher consumption or
 * a further chainable field for transforms like `lower()` / `len()`.
 */
final class StringFieldTest extends TestCase
{
  public function testEqualsAcceptsExactMatch(): void
  {
    // `F->firstName->equals('Alice')` → accept when the User's firstName
    // is exactly `'Alice'`. Reject for any other value.
    $filter = (new StringField(MagicFilter::root()->firstName))->equals('Alice');

    self::assertTrue($filter($this->user(firstName: 'Alice')));
    self::assertFalse($filter($this->user(firstName: 'Bob')));
  }

  public function testContainsAcceptsSubstring(): void
  {
    // `contains('foo')` matches any string containing `'foo'`. Substring
    // matching delegates to PHP's `str_contains` under the hood.
    $filter = (new StringField(MagicFilter::root()->firstName))->contains('li');

    self::assertTrue($filter($this->user(firstName: 'Alice')));
    self::assertFalse($filter($this->user(firstName: 'Bob')));
  }

  public function testStartsWithAcceptsPrefix(): void
  {
    $filter = (new StringField(MagicFilter::root()->firstName))->startsWith('Al');

    self::assertTrue($filter($this->user(firstName: 'Alice')));
    self::assertFalse($filter($this->user(firstName: 'Bob')));
  }

  public function testEndsWithAcceptsSuffix(): void
  {
    $filter = (new StringField(MagicFilter::root()->firstName))->endsWith('ce');

    self::assertTrue($filter($this->user(firstName: 'Alice')));
    self::assertFalse($filter($this->user(firstName: 'Bob')));
  }

  public function testInAcceptsAnyOfTheGivenValues(): void
  {
    // Membership: accept when the field's value matches any entry in the
    // haystack. Equivalent to upstream `F.first_name.in_({'Alice', 'Bob'})`.
    $filter = (new StringField(MagicFilter::root()->firstName))->in(['Alice', 'Bob']);

    self::assertTrue($filter($this->user(firstName: 'Alice')));
    self::assertTrue($filter($this->user(firstName: 'Bob')));
    self::assertFalse($filter($this->user(firstName: 'Carol')));
  }

  public function testLenReturnsIntField(): void
  {
    // `len()` is a chain transform (not a terminal). It extends the chain
    // with the string-length projection and surfaces a new `IntField` so
    // call sites can chain int comparators.
    $field = (new StringField(MagicFilter::root()->firstName))->len();

    self::assertInstanceOf(IntField::class, $field);
  }

  public function testLenComparedToInteger(): void
  {
    // End-to-end: `F->firstName->len()->gt(3)` accepts a 5-letter name
    // and rejects a 3-letter one.
    $filter = (new StringField(MagicFilter::root()->firstName))->len()->gt(3);

    self::assertTrue($filter($this->user(firstName: 'Alice')));
    self::assertFalse($filter($this->user(firstName: 'Bob')));
  }

  public function testLowerProducesNewStringField(): void
  {
    // `lower()` extends the chain with the lowercase transform and
    // returns a fresh `StringField` so subsequent comparators see the
    // lowercased running value.
    $field = (new StringField(MagicFilter::root()->firstName))->lower();

    self::assertInstanceOf(StringField::class, $field);
  }

  public function testLowerEqualsMatchesCaseInsensitiveString(): void
  {
    // `F->firstName->lower()->equals('hello')` accepts `'HELLO'` because
    // the transform lowercases the running value before the equality
    // comparator runs.
    $filter = (new StringField(MagicFilter::root()->firstName))
      ->lower()
      ->equals('hello');

    self::assertTrue($filter($this->user(firstName: 'HELLO')));
    self::assertTrue($filter($this->user(firstName: 'hello')));
    self::assertFalse($filter($this->user(firstName: 'world')));
  }

  public function testUpperProducesNewStringField(): void
  {
    $field = (new StringField(MagicFilter::root()->firstName))->upper();

    self::assertInstanceOf(StringField::class, $field);
  }

  public function testUpperEqualsMatchesUppercasedString(): void
  {
    // Mirror image of `lower()`: `upper()->equals('HELLO')` accepts a
    // lowercase subject because the chain uppercases first.
    $filter = (new StringField(MagicFilter::root()->firstName))
      ->upper()
      ->equals('HELLO');

    self::assertTrue($filter($this->user(firstName: 'hello')));
    self::assertTrue($filter($this->user(firstName: 'HELLO')));
    self::assertFalse($filter($this->user(firstName: 'world')));
  }

  public function testChainImmutabilityLowerDoesNotMutateOriginal(): void
  {
    // Each chain operation clones — calling `lower()` on a field must
    // not mutate the original chain or the field instance.
    $original = new StringField(MagicFilter::root()->firstName);
    $derived = $original->lower();

    self::assertNotSame($original, $derived);
    self::assertNotSame($original->chain, $derived->chain);
  }

  /**
   * Build a `User` event with the given `firstName`. We use the
   * existing top-level TelegramObject type so MagicFilter's
   * `GetAttributeOperation` walks the real property — matching how
   * the F-DSL is consumed by the dispatcher in production.
   */
  private function user(string $firstName): User
  {
    return new User(id: 1, isBot: false, firstName: $firstName);
  }
}
