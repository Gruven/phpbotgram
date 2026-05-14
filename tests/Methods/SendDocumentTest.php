<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Methods;

use Gruven\PhpBotGram\Methods\SendDocument;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Types\BufferedInputFile;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\Message;
use PHPUnit\Framework\TestCase;

/**
 * Upstream: tests/test_api/test_methods/test_send_document.py
 *
 * Upstream skips:
 *   - async multipart upload assertions — API divergence (a): upload path covered
 *     by InputFileTest and BotDownloadTest.
 *   - Pydantic model_dump — API divergence (a).
 */
final class SendDocumentTest extends TestCase
{
  // ── constructor shape ────────────────────────────────────────────────────────

  public function testWithFileIdString(): void
  {
    $method = new SendDocument(chatId: 42, document: 'BQACAgIA');
    self::assertSame(42, $method->chatId);
    self::assertSame('BQACAgIA', $method->document);
    self::assertNull($method->caption);
    self::assertNull($method->thumbnail);
    self::assertNull($method->disableContentTypeDetection);
  }

  public function testWithBufferedInputFile(): void
  {
    $file = new BufferedInputFile('pdf content', 'report.pdf');
    $method = new SendDocument(chatId: 1, document: $file, caption: 'Annual report');
    self::assertSame($file, $method->document);
    self::assertSame('Annual report', $method->caption);
  }

  public function testWithThumbnail(): void
  {
    $thumb = new BufferedInputFile('thumb data', 'thumb.jpg');
    $method = new SendDocument(chatId: 1, document: 'BQACAgIA', thumbnail: $thumb);
    self::assertSame($thumb, $method->thumbnail);
  }

  public function testApiMethodConstant(): void
  {
    self::assertSame('sendDocument', SendDocument::ApiMethod);
  }

  // ── MockedBot round-trip ─────────────────────────────────────────────────────

  public function testRoundTripReturnsMessage(): void
  {
    $bot = new MockedBot();
    $expected = new Message(
      messageId: 15,
      date: new DateTime('@0'),
      chat: new Chat(id: 10, type: 'private'),
    );
    $bot->addResultFor(SendDocument::class, ok: true, result: $expected);

    $result = $bot->sendDocument(chatId: 10, document: 'doc_file_id');

    self::assertInstanceOf(Message::class, $result);
    self::assertSame(15, $result->messageId);
  }

  public function testGetRequestCapturesDocument(): void
  {
    $bot = new MockedBot();
    $bot->addResultFor(
      SendDocument::class,
      ok: true,
      result: new Message(messageId: 1, date: new DateTime('@0'), chat: new Chat(id: 1, type: 'private')),
    );

    $bot->sendDocument(chatId: 1, document: 'docid_xyz', caption: 'Here you go');

    $sent = $bot->getRequest();
    self::assertInstanceOf(SendDocument::class, $sent);
    self::assertSame('docid_xyz', $sent->document);
    self::assertSame('Here you go', $sent->caption);
  }
}
