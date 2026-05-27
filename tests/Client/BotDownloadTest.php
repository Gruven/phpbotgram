<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client;

use Gruven\PhpBotGram\Methods\GetFile;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Types\File;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
final class BotDownloadTest extends TestCase
{
  public function testDownloadFileReturnsBufferedBody(): void
  {
    $bot = new MockedBot();
    $file = new File(fileId: 'abc', fileUniqueId: 'abc-u', filePath: 'photos/file_1.jpg');
    $url = $bot->session->api->fileUrl($bot->token, 'photos/file_1.jpg');
    $session = $bot->getMockedSession();
    $session->cannedStreamBodies[$url] = 'hello';

    $body = $bot->downloadFile($file);
    self::assertSame('hello', $body);
    self::assertContains($url, $session->streamedUrls);
  }

  public function testDownloadFileWritesToPath(): void
  {
    $bot = new MockedBot();
    $file = new File(fileId: 'abc', fileUniqueId: 'abc-u', filePath: 'photos/file_2.jpg');
    $url = $bot->session->api->fileUrl($bot->token, 'photos/file_2.jpg');
    $bot->getMockedSession()->cannedStreamBodies[$url] = 'contents';

    $tmp = (string)tempnam(sys_get_temp_dir(), 'phpbg-dl');

    try {
      $result = $bot->downloadFile($file, $tmp);
      self::assertNull($result, 'downloadFile returns null when destination is a path');
      self::assertSame('contents', (string)file_get_contents($tmp));
    } finally {
      @unlink($tmp);
    }
  }

  public function testDownloadFileThrowsOnUnopenableDestination(): void
  {
    $bot = new MockedBot();
    $file = new File(fileId: 'abc', fileUniqueId: 'abc-u', filePath: 'photos/file_3.jpg');
    $url = $bot->session->api->fileUrl($bot->token, 'photos/file_3.jpg');
    $bot->getMockedSession()->cannedStreamBodies[$url] = 'data';

    $this->expectException(RuntimeException::class);
    $bot->downloadFile($file, '/no/such/dir/should-fail.bin');
  }

  public function testDownloadAcceptsBareFileId(): void
  {
    $bot = new MockedBot();
    // Queue the response for GetFile.
    $file = new File(fileId: 'fid-1', fileUniqueId: 'fid-1-u', filePath: 'photos/x.jpg');
    $bot->addResultFor(GetFile::class, ok: true, result: $file);
    $url = $bot->session->api->fileUrl($bot->token, 'photos/x.jpg');
    $bot->getMockedSession()->cannedStreamBodies[$url] = 'BODY';

    self::assertSame('BODY', $bot->download('fid-1'));
  }
}
