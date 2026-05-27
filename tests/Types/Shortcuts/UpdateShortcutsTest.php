<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Types\Shortcuts;

use Gruven\PhpBotGram\Types\CallbackQuery;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\InlineQuery;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\Update;
use Gruven\PhpBotGram\Types\User;
use PHPUnit\Framework\TestCase;

/**
 * Cycle 4 I2 fix: `Update::eventType()` returns the wire-name string
 * (`'message'`, `'callback_query'`, …) of whichever optional field is
 * non-null, or `null` when no recognised field is populated.
 *
 * Mirrors aiogram's `Update.event_type` accessor — it routes the
 * dispatcher's per-event handler chain without forcing the handler to
 * walk every optional slot manually.
 *
 * @internal
 *
 * @coversNothing
 */
final class UpdateShortcutsTest extends TestCase
{
  public function testEventTypeMessage(): void
  {
    $chat = new Chat(id: 1, type: 'private');
    $update = new Update(
      updateId: 1,
      message: new Message(messageId: 1, date: new DateTime('@0'), chat: $chat),
    );

    self::assertSame('message', $update->eventType());
  }

  public function testEventTypeCallbackQuery(): void
  {
    $update = new Update(
      updateId: 2,
      callbackQuery: new CallbackQuery(
        id: 'cq',
        fromUser: new User(id: 1, isBot: false, firstName: 'Ada'),
        chatInstance: 'inst',
      ),
    );

    self::assertSame('callback_query', $update->eventType());
  }

  public function testEventTypeInlineQuery(): void
  {
    $update = new Update(
      updateId: 3,
      inlineQuery: new InlineQuery(
        id: 'iq',
        fromUser: new User(id: 1, isBot: false, firstName: 'Ada'),
        query: '',
        offset: '',
      ),
    );

    self::assertSame('inline_query', $update->eventType());
  }

  public function testEventTypeEditedMessage(): void
  {
    $chat = new Chat(id: 1, type: 'private');
    $update = new Update(
      updateId: 4,
      editedMessage: new Message(messageId: 1, date: new DateTime('@0'), chat: $chat),
    );

    self::assertSame('edited_message', $update->eventType());
  }

  public function testEventTypeReturnsNullWhenNoFieldsPopulated(): void
  {
    $update = new Update(updateId: 0);
    self::assertNull($update->eventType());
  }

  public function testEventTypeMatchesFirstNonNullFieldInDeclarationOrder(): void
  {
    // When two fields are populated (a deliberately-unusual case), the
    // wire-name from the earlier-declared field wins — declaration order
    // matches the schema's optional-field order so a Telegram payload
    // never carries two of these slots at once. The guarantee is purely
    // defensive against tests / synthetic payloads.
    $chat = new Chat(id: 1, type: 'private');
    $update = new Update(
      updateId: 5,
      message: new Message(messageId: 1, date: new DateTime('@0'), chat: $chat),
      callbackQuery: new CallbackQuery(
        id: 'cq',
        fromUser: new User(id: 1, isBot: false, firstName: 'Ada'),
        chatInstance: 'inst',
      ),
    );

    self::assertSame('message', $update->eventType());
  }
}
