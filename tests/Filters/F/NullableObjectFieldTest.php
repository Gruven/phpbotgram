<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Filters\F;

use Gruven\PhpBotGram\Filters\F\NullableObjectField;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\User;
use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilter;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for `NullableObjectField` — typed wrapper for nullable nested
 * object fields (`Message::$fromUser: ?User`, `Message::$replyToMessage:
 * ?Message`, …). The wrapper exposes only presence-tests because deeper
 * predicates are field-specific and belong on per-type sub-builders.
 */
final class NullableObjectFieldTest extends TestCase
{
  public function testIsSetAcceptsNonNullObject(): void
  {
    $filter = (new NullableObjectField(MagicFilter::root()->fromUser))->isSet();

    self::assertTrue($filter($this->message(fromUser: $this->user())));
    self::assertFalse($filter($this->message(fromUser: null)));
  }

  public function testIsNullAcceptsNullObject(): void
  {
    $filter = (new NullableObjectField(MagicFilter::root()->fromUser))->isNull();

    self::assertTrue($filter($this->message(fromUser: null)));
    self::assertFalse($filter($this->message(fromUser: $this->user())));
  }

  private function message(?User $fromUser): Message
  {
    return new Message(
      messageId: 1,
      date: new DateTime('2024-01-01'),
      chat: new Chat(id: 1, type: 'private'),
      fromUser: $fromUser,
    );
  }

  private function user(): User
  {
    return new User(id: 1, isBot: false, firstName: 'Alice');
  }
}
