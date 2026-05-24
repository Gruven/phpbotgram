<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Methods;

use Gruven\PhpBotGram\Methods\SendPhoto;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Types\BufferedInputFile;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\Message;
use PHPUnit\Framework\TestCase;

/**
 * Upstream: tests/test_api/test_methods/test_send_photo.py
 *
 * Upstream skips:
 *   - async multipart upload assertions (AsyncMock on make_request) — API
 *     divergence (a): PHP's InputFile upload path is covered by InputFileTest
 *     and BotDownloadTest.
 *   - Pydantic model_dump — API divergence (a).
 *
 * @internal
 */
final class SendPhotoTest extends TestCase
{
  // ── constructor shape ────────────────────────────────────────────────────────

  public function testWithFileIdString(): void
  {
    $method = new SendPhoto(chatId: 123, photo: 'AgACAgIAAA');
    self::assertSame(123, $method->chatId);
    self::assertSame('AgACAgIAAA', $method->photo);
    self::assertNull($method->caption);
    self::assertNull($method->replyMarkup);
  }

  public function testWithInputFile(): void
  {
    $file = new BufferedInputFile('bytes', 'photo.jpg');
    $method = new SendPhoto(chatId: 1, photo: $file, caption: 'Look at this');
    self::assertSame($file, $method->photo);
    self::assertSame('Look at this', $method->caption);
  }

  public function testApiMethodConstant(): void
  {
    self::assertSame('sendPhoto', SendPhoto::ApiMethod);
  }

  // ── MockedBot round-trip ─────────────────────────────────────────────────────

  public function testRoundTripReturnsMessage(): void
  {
    $bot = new MockedBot();
    $expected = new Message(
      messageId: 7,
      date: new DateTime('@0'),
      chat: new Chat(id: 5, type: 'private'),
      caption: 'Look at this',
    );
    $bot->addResultFor(SendPhoto::class, ok: true, result: $expected);

    $result = $bot->sendPhoto(chatId: 5, photo: 'file_id_123', caption: 'Look at this');

    self::assertInstanceOf(Message::class, $result);
    self::assertSame(7, $result->messageId);
    self::assertSame('Look at this', $result->caption);
  }

  public function testGetRequestCapturesPhoto(): void
  {
    $bot = new MockedBot();
    $bot->addResultFor(
      SendPhoto::class,
      ok: true,
      result: new Message(messageId: 1, date: new DateTime('@0'), chat: new Chat(id: 1, type: 'private')),
    );

    $bot->sendPhoto(chatId: 1, photo: 'file_abc');

    $sent = $bot->getRequest();
    self::assertInstanceOf(SendPhoto::class, $sent);
    self::assertSame('file_abc', $sent->photo);
  }
}
