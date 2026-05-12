<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Types\Shortcuts;

use Gruven\PhpBotGram\Types\CallbackQuery;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\InaccessibleMessage;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\User;
use PHPUnit\Framework\TestCase;

/**
 * Cycle 4 I1 fix: hand-authored `CallbackQuery::messageId()` helper.
 * Mirrors aiogram's `CallbackQuery.message` access pattern: the
 * `message` property is a `MaybeInaccessibleMessage` union — either a
 * full `Message` or an `InaccessibleMessage` stub — and the helper
 * unwraps the `messageId` of whichever side is present, or returns
 * `null` when there's no underlying message at all.
 *
 * @internal
 *
 * @coversNothing
 */
final class CallbackQueryShortcutsTest extends TestCase
{
  public function testMessageIdFromMessage(): void
  {
    $user = new User(id: 1, isBot: false, firstName: 'Ada');
    $chat = new Chat(id: 1, type: 'private');
    $cq = new CallbackQuery(
      id: 'cq-1',
      fromUser: $user,
      chatInstance: 'inst',
      message: new Message(messageId: 555, date: new DateTime('@0'), chat: $chat),
    );

    self::assertSame(555, $cq->messageId());
  }

  public function testMessageIdFromInaccessibleMessage(): void
  {
    $user = new User(id: 1, isBot: false, firstName: 'Ada');
    $chat = new Chat(id: 1, type: 'private');
    $cq = new CallbackQuery(
      id: 'cq-1',
      fromUser: $user,
      chatInstance: 'inst',
      message: new InaccessibleMessage(chat: $chat, messageId: 99, date: 0),
    );

    self::assertSame(99, $cq->messageId());
  }

  public function testMessageIdReturnsNullWhenMessageMissing(): void
  {
    $user = new User(id: 1, isBot: false, firstName: 'Ada');
    $cq = new CallbackQuery(
      id: 'cq-1',
      fromUser: $user,
      chatInstance: 'inst',
    );

    self::assertNull($cq->messageId());
  }
}
