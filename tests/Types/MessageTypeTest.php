<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Types;

use Gruven\PhpBotGram\Methods\EditMessageText;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\User;
use PHPUnit\Framework\TestCase;

/**
 * Upstream: tests/test_api/test_types/test_message.py
 *
 * Upstream skips:
 *   - Pydantic model_validate / model_dump — API divergence (a): handled by
 *     Serializer::unpack() + Session::prepareValue(), covered in
 *     BaseSessionTest and SerializerTest.
 *   - async `de_json` helper — API divergence (a).
 *   - parse_entities / parse_caption_entities (Python async generators) —
 *     API divergence (a): PHP does not expose these helpers; callers
 *     operate on the `$entities` / `$captionEntities` arrays directly.
 *   - effective_attachment computed property — API divergence (a): not ported.
 *
 * @internal
 */
final class MessageTypeTest extends TestCase
{
  // ── construction ─────────────────────────────────────────────────────────────

  public function testMinimalTextMessage(): void
  {
    $msg = new Message(
      messageId: 1,
      date: new DateTime('@0'),
      chat: new Chat(id: 10, type: 'private'),
      text: 'Hello',
    );
    self::assertSame(1, $msg->messageId);
    self::assertSame('Hello', $msg->text);
    self::assertSame(10, $msg->chat->id);
    self::assertNull($msg->fromUser);
    self::assertNull($msg->caption);
    self::assertNull($msg->entities);
  }

  public function testMessageWithFromUser(): void
  {
    $user = new User(id: 99, isBot: false, firstName: 'Alice');
    $msg = new Message(
      messageId: 5,
      date: new DateTime('@1000'),
      chat: new Chat(id: 1, type: 'private'),
      fromUser: $user,
      text: 'Hi there',
    );
    self::assertNotNull($msg->fromUser);
    self::assertSame(99, $msg->fromUser->id);
    self::assertSame('Alice', $msg->fromUser->firstName);
  }

  public function testMessageDate(): void
  {
    $ts = 1_700_000_000;
    $msg = new Message(
      messageId: 2,
      date: DateTime::fromTimestamp($ts),
      chat: new Chat(id: 1, type: 'group'),
    );
    self::assertSame($ts, $msg->date->toTimestamp());
  }

  public function testMessageWithCaption(): void
  {
    $msg = new Message(
      messageId: 3,
      date: new DateTime('@0'),
      chat: new Chat(id: 1, type: 'private'),
      caption: 'Photo caption',
    );
    self::assertSame('Photo caption', $msg->caption);
    self::assertNull($msg->text);
  }

  public function testMessageWithReplyToMessage(): void
  {
    $original = new Message(
      messageId: 1,
      date: new DateTime('@0'),
      chat: new Chat(id: 1, type: 'private'),
      text: 'Original',
    );
    $reply = new Message(
      messageId: 2,
      date: new DateTime('@1'),
      chat: new Chat(id: 1, type: 'private'),
      text: 'Reply',
      replyToMessage: $original,
    );
    self::assertNotNull($reply->replyToMessage);
    self::assertSame(1, $reply->replyToMessage->messageId);
    self::assertSame('Original', $reply->replyToMessage->text);
  }

  public function testEditTextShortcutKeepsTextAsFirstPositionalArgument(): void
  {
    $msg = new Message(
      messageId: 4,
      date: new DateTime('@0'),
      chat: new Chat(id: 1, type: 'private'),
      text: 'Original',
    );

    $method = $msg->editText('Order confirmed');

    self::assertInstanceOf(EditMessageText::class, $method);
    self::assertSame('Order confirmed', $method->text);
    self::assertNull($method->inlineMessageId);
  }
}
