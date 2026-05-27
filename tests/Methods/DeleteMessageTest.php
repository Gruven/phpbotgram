<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Methods;

use Gruven\PhpBotGram\Methods\DeleteMessage;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use PHPUnit\Framework\TestCase;

/**
 * Upstream: tests/test_api/test_methods/test_delete_message.py
 *
 * Upstream skips:
 *   - async infrastructure — API divergence (a)/test infrastructure divergence (c).
 *   - Pydantic model_dump — API divergence (a).
 *
 * @internal
 */
final class DeleteMessageTest extends TestCase
{
  // ── constructor shape ────────────────────────────────────────────────────────

  public function testRequiredArgs(): void
  {
    $method = new DeleteMessage(chatId: 100, messageId: 55);
    self::assertSame(100, $method->chatId);
    self::assertSame(55, $method->messageId);
  }

  public function testWithStringChatId(): void
  {
    $method = new DeleteMessage(chatId: '@channel', messageId: 1);
    self::assertSame('@channel', $method->chatId);
  }

  public function testApiMethodConstant(): void
  {
    self::assertSame('deleteMessage', DeleteMessage::ApiMethod);
  }

  public function testReturnsTypeBool(): void
  {
    self::assertSame('bool', DeleteMessage::ReturnsType);
  }

  // ── MockedBot round-trip ─────────────────────────────────────────────────────

  public function testRoundTripReturnsTrue(): void
  {
    $bot = new MockedBot();
    $bot->addResultFor(DeleteMessage::class, ok: true, result: true);

    $result = $bot->deleteMessage(chatId: 100, messageId: 55);

    self::assertTrue($result);
  }

  public function testGetRequestCapturesChatAndMessageId(): void
  {
    $bot = new MockedBot();
    $bot->addResultFor(DeleteMessage::class, ok: true, result: true);

    $bot->deleteMessage(chatId: 200, messageId: 99);

    $sent = $bot->getRequest();
    self::assertInstanceOf(DeleteMessage::class, $sent);
    self::assertSame(200, $sent->chatId);
    self::assertSame(99, $sent->messageId);
  }
}
