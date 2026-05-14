<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Methods;

use Gruven\PhpBotGram\Methods\SendMediaGroup;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\InputMediaDocument;
use Gruven\PhpBotGram\Types\InputMediaPhoto;
use Gruven\PhpBotGram\Types\Message;
use PHPUnit\Framework\TestCase;

/**
 * Upstream: tests/test_api/test_methods/test_send_media_group.py
 *
 * Upstream skips:
 *   - async multipart upload assertions — API divergence (a): upload path covered
 *     by InputFileTest.
 *   - Pydantic model_dump for InputMedia variants — API divergence (a).
 */
final class SendMediaGroupTest extends TestCase
{
  // ── constructor shape ────────────────────────────────────────────────────────

  public function testWithPhotoAlbum(): void
  {
    $media = [
      new InputMediaPhoto(media: 'photo_file_id_1'),
      new InputMediaPhoto(media: 'photo_file_id_2', caption: 'Second photo'),
    ];
    $method = new SendMediaGroup(chatId: 1, media: $media);
    self::assertSame(1, $method->chatId);
    self::assertCount(2, $method->media);
    self::assertSame('photo_file_id_1', $method->media[0]->media);
  }

  public function testWithDocumentAlbum(): void
  {
    $media = [
      new InputMediaDocument(media: 'doc_id_1'),
      new InputMediaDocument(media: 'doc_id_2'),
    ];
    $method = new SendMediaGroup(chatId: 42, media: $media);
    self::assertCount(2, $method->media);
    self::assertInstanceOf(InputMediaDocument::class, $method->media[0]);
  }

  public function testApiMethodConstant(): void
  {
    self::assertSame('sendMediaGroup', SendMediaGroup::ApiMethod);
  }

  public function testReturnsTypeIsListMessage(): void
  {
    self::assertSame('list:Message', SendMediaGroup::ReturnsType);
  }

  // ── MockedBot round-trip ─────────────────────────────────────────────────────

  public function testRoundTripReturnsMessageList(): void
  {
    $bot = new MockedBot();
    $messages = [
      new Message(messageId: 1, date: new DateTime('@0'), chat: new Chat(id: 1, type: 'private')),
      new Message(messageId: 2, date: new DateTime('@0'), chat: new Chat(id: 1, type: 'private')),
    ];
    // list:Message — MockedBot type-check skipped for non-class ReturnsType.
    $bot->addResultFor(SendMediaGroup::class, ok: true, result: $messages);

    $result = $bot->sendMediaGroup(
      chatId: 1,
      media: [
        new InputMediaPhoto(media: 'photo1'),
        new InputMediaPhoto(media: 'photo2'),
      ],
    );

    self::assertCount(2, $result);
    self::assertSame(1, $result[0]->messageId);
    self::assertSame(2, $result[1]->messageId);
  }

  public function testGetRequestCapturesMedia(): void
  {
    $bot = new MockedBot();
    $bot->addResultFor(SendMediaGroup::class, ok: true, result: [
      new Message(messageId: 1, date: new DateTime('@0'), chat: new Chat(id: 1, type: 'private')),
    ]);

    $bot->sendMediaGroup(chatId: 5, media: [new InputMediaPhoto(media: 'img')]);

    $sent = $bot->getRequest();
    self::assertInstanceOf(SendMediaGroup::class, $sent);
    self::assertSame(5, $sent->chatId);
    self::assertCount(1, $sent->media);
  }
}
