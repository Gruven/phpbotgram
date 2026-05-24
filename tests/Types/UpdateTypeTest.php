<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Types;

use Gruven\PhpBotGram\Types\CallbackQuery;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\Update;
use Gruven\PhpBotGram\Types\User;
use PHPUnit\Framework\TestCase;

/**
 * Upstream: tests/test_api/test_types/test_update.py
 *
 * Upstream skips:
 *   - Pydantic model_validate / model_dump — API divergence (a).
 *   - effective_message / effective_user / effective_chat computed properties
 *     (Python @property) — API divergence (a): PHP exposes equivalent logic
 *     via UpdateShortcuts; those are tested in tests/Types/Shortcuts/UpdateShortcutsTest.php.
 *   - de_json helper — API divergence (a).
 *
 * @internal
 */
final class UpdateTypeTest extends TestCase
{
  // ── construction ─────────────────────────────────────────────────────────────

  public function testUpdateWithMessage(): void
  {
    $msg = new Message(messageId: 1, date: new DateTime('@0'), chat: new Chat(id: 1, type: 'private'), text: 'hi');
    $update = new Update(updateId: 100, message: $msg);
    self::assertSame(100, $update->updateId);
    self::assertSame($msg, $update->message);
    self::assertNull($update->callbackQuery);
    self::assertNull($update->editedMessage);
  }

  public function testUpdateWithCallbackQuery(): void
  {
    $user = new User(id: 1, isBot: false, firstName: 'A');
    $cq = new CallbackQuery(id: 'cq1', fromUser: $user, chatInstance: 'inst', data: 'btn_click');
    $update = new Update(updateId: 200, callbackQuery: $cq);
    self::assertSame(200, $update->updateId);
    self::assertSame($cq, $update->callbackQuery);
    self::assertNull($update->message);
  }

  public function testUpdateIdOnlyMinimal(): void
  {
    $update = new Update(updateId: 1);
    self::assertSame(1, $update->updateId);
    self::assertNull($update->message);
    self::assertNull($update->editedMessage);
    self::assertNull($update->callbackQuery);
    self::assertNull($update->poll);
  }

  public function testUpdateWithEditedMessage(): void
  {
    $msg = new Message(messageId: 5, date: new DateTime('@0'), chat: new Chat(id: 2, type: 'group'), text: 'edited');
    $update = new Update(updateId: 300, editedMessage: $msg);
    self::assertNull($update->message);
    self::assertSame(5, $update->editedMessage?->messageId);
  }
}
