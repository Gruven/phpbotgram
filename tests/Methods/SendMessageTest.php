<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Methods;

use Gruven\PhpBotGram\Client\BotDefault;
use Gruven\PhpBotGram\Methods\SendMessage;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\InlineKeyboardButton;
use Gruven\PhpBotGram\Types\InlineKeyboardMarkup;
use Gruven\PhpBotGram\Types\Message;
use PHPUnit\Framework\TestCase;

/**
 * Upstream: tests/test_api/test_methods/test_send_message.py
 *
 * Upstream skips:
 *   - async test infrastructure (pytest-asyncio, AsyncMock) — API divergence (a)/
 *     test infrastructure divergence (c): PHP uses synchronous MockedBot.
 *   - Pydantic model_dump / model_validate serialization asserts — API divergence (a):
 *     replaced by constructor-shape verification and MockedBot round-trip.
 *   - parse_mode / link_preview_options BotDefault propagation tests — codegen-
 *     determined behavior (b): the BotDefault mechanism is exhaustively tested in
 *     tests/Client/Session/BaseSessionTest.php.
 *
 * @internal
 */
final class SendMessageTest extends TestCase
{
  // ── constructor shape ────────────────────────────────────────────────────────

  public function testRequiredArgsOnly(): void
  {
    $method = new SendMessage(chatId: 123, text: 'Hello');
    self::assertSame(123, $method->chatId);
    self::assertSame('Hello', $method->text);
    // parseMode defaults to BotDefault('parse_mode'), not null
    self::assertInstanceOf(BotDefault::class, $method->parseMode);
    self::assertNull($method->entities);
    self::assertNull($method->replyMarkup);
  }

  public function testWithStringChatId(): void
  {
    $method = new SendMessage(chatId: '@channelusername', text: 'Hi');
    self::assertSame('@channelusername', $method->chatId);
  }

  public function testWithReplyMarkup(): void
  {
    $markup = new InlineKeyboardMarkup(
      inlineKeyboard: [[new InlineKeyboardButton(text: 'Click', callbackData: 'cb_data')]],
    );
    $method = new SendMessage(chatId: 1, text: 'Pick one', replyMarkup: $markup);
    self::assertSame($markup, $method->replyMarkup);
  }

  public function testApiMethodConstant(): void
  {
    self::assertSame('sendMessage', SendMessage::ApiMethod);
  }

  // ── MockedBot round-trip ─────────────────────────────────────────────────────

  public function testRoundTripViaBot(): void
  {
    $bot = new MockedBot();
    $expected = new Message(
      messageId: 42,
      date: new DateTime('@0'),
      chat: new Chat(id: 100, type: 'private'),
      text: 'Hello from bot',
    );
    $bot->addResultFor(SendMessage::class, ok: true, result: $expected);

    $result = $bot->sendMessage(chatId: 100, text: 'Hello from bot');

    self::assertInstanceOf(Message::class, $result);
    self::assertSame(42, $result->messageId);
    self::assertSame('Hello from bot', $result->text);
  }

  public function testGetRequestCapture(): void
  {
    $bot = new MockedBot();
    $bot->addResultFor(
      SendMessage::class,
      ok: true,
      result: new Message(messageId: 1, date: new DateTime('@0'), chat: new Chat(id: 1, type: 'private')),
    );

    $bot->sendMessage(chatId: 1, text: 'capture me', parseMode: 'HTML');

    $sent = $bot->getRequest();
    self::assertInstanceOf(SendMessage::class, $sent);
    self::assertSame('capture me', $sent->text);
    self::assertSame('HTML', $sent->parseMode);
  }
}
