<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Methods;

use Gruven\PhpBotGram\Methods\EditMessageText;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\Message;
use PHPUnit\Framework\TestCase;

/**
 * Upstream: tests/test_api/test_methods/test_edit_message_text.py
 *
 * Upstream skips:
 *   - async infrastructure — API divergence (a)/test infrastructure divergence (c).
 *   - Pydantic model_dump — API divergence (a).
 *   - BotDefault parse_mode/link_preview propagation — codegen-determined
 *     behavior (b): covered by BaseSessionTest.
 */
final class EditMessageTextTest extends TestCase
{
  // ── constructor shape ────────────────────────────────────────────────────────

  public function testRequiredTextOnly(): void
  {
    $method = new EditMessageText(text: 'Updated text');
    self::assertSame('Updated text', $method->text);
    self::assertNull($method->chatId);
    self::assertNull($method->messageId);
    self::assertNull($method->inlineMessageId);
    self::assertNull($method->replyMarkup);
  }

  public function testWithChatAndMessageId(): void
  {
    $method = new EditMessageText(text: 'Updated', chatId: 100, messageId: 42);
    self::assertSame('Updated', $method->text);
    self::assertSame(100, $method->chatId);
    self::assertSame(42, $method->messageId);
  }

  public function testWithInlineMessageId(): void
  {
    $method = new EditMessageText(text: 'Inline edit', inlineMessageId: 'inline123');
    self::assertSame('Inline edit', $method->text);
    self::assertSame('inline123', $method->inlineMessageId);
    self::assertNull($method->chatId);
    self::assertNull($method->messageId);
  }

  public function testApiMethodConstant(): void
  {
    self::assertSame('editMessageText', EditMessageText::ApiMethod);
  }

  public function testReturnsTypeIsUnion(): void
  {
    self::assertSame('union:Message|bool', EditMessageText::ReturnsType);
  }

  // ── MockedBot round-trip ─────────────────────────────────────────────────────

  public function testRoundTripReturnsMessage(): void
  {
    $bot = new MockedBot();
    $expected = new Message(
      messageId: 42,
      date: new DateTime('@0'),
      chat: new Chat(id: 1, type: 'private'),
      text: 'Updated text',
    );
    $bot->addResultFor(EditMessageText::class, ok: true, result: $expected);

    $result = $bot->editMessageText(text: 'Updated text', chatId: 1, messageId: 42);

    self::assertInstanceOf(Message::class, $result);
    self::assertSame(42, $result->messageId);
    self::assertSame('Updated text', $result->text);
  }

  public function testRoundTripReturnsTrueForInlineEdit(): void
  {
    $bot = new MockedBot();
    // Inline edits return bool, not Message; MockedBot skips type-check for
    // 'union:...' ReturnsType (class_exists returns false).
    $bot->addResultFor(EditMessageText::class, ok: true, result: true);

    $result = $bot->editMessageText(text: 'Edited inline', inlineMessageId: 'inline_abc');

    self::assertTrue($result);
  }

  public function testGetRequestCapturesText(): void
  {
    $bot = new MockedBot();
    $bot->addResultFor(
      EditMessageText::class,
      ok: true,
      result: new Message(messageId: 1, date: new DateTime('@0'), chat: new Chat(id: 1, type: 'private')),
    );

    $bot->editMessageText(text: 'New text', chatId: 5, messageId: 10, parseMode: 'MarkdownV2');

    $sent = $bot->getRequest();
    self::assertInstanceOf(EditMessageText::class, $sent);
    self::assertSame('New text', $sent->text);
    self::assertSame('MarkdownV2', $sent->parseMode);
  }
}
