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
