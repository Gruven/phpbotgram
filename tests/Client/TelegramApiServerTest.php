<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client;

use Gruven\PhpBotGram\Client\TelegramApiServer;
use PHPUnit\Framework\TestCase;

/**
 * Upstream: tests/test_api/test_client/test_api_server.py
 *
 * Upstream skips:
 *   - TestBareFilesPathWrapper::test_to_local / test_to_server — API divergence (a):
 *     PHP does not implement a BareFilesPathWrapper; path translation is handled
 *     inside AmphpSession::streamContent for local-server mode.
 *   - TestSimpleFilesPathWrapper::test_to_local / test_to_server — API divergence (a):
 *     same reason; the SimpleFilesPathWrapper concept is aiohttp-specific.
 *   - test_file_url[Path("path")] — API divergence (a): PHP has no `pathlib.Path`;
 *     the string-path variant is covered by testProductionFileUrl.
 *   - test_from_base[Path("path")] — same as above.
 */
final class TelegramApiServerTest extends TestCase
{
  // ── TestAPIServer ────────────────────────────────────────────────────────────

  /** Upstream: test_method_url — PRODUCTION.api_url */
  public function testProductionMethodUrl(): void
  {
    $api = TelegramApiServer::production();
    self::assertSame(
      'https://api.telegram.org/bot42:TEST/apiMethod',
      $api->apiUrl('42:TEST', 'apiMethod'),
    );
  }

  /** Upstream: test_file_url[path="path"] */
  public function testProductionFileUrl(): void
  {
    $api = TelegramApiServer::production();
    self::assertSame(
      'https://api.telegram.org/file/bot42:TEST/path',
      $api->fileUrl('42:TEST', 'path'),
    );
  }

  /** Upstream: test_from_base[path="path"] */
  public function testFromBaseMethodAndFileUrlAndIsLocal(): void
  {
    $api = TelegramApiServer::fromBase('http://localhost:8081', isLocal: true);

    self::assertSame(
      'http://localhost:8081/bot42:TEST/apiMethod',
      $api->apiUrl('42:TEST', 'apiMethod'),
    );
    self::assertSame(
      'http://localhost:8081/file/bot42:TEST/path',
      $api->fileUrl('42:TEST', 'path'),
    );
    self::assertTrue($api->isLocal);
  }

  // ── Legacy / composite assertions (kept from pre-task baseline) ──────────────

  public function testProductionUrls(): void
  {
    $api = TelegramApiServer::production();
    self::assertSame('https://api.telegram.org/bot123:abc/sendMessage', $api->apiUrl('123:abc', 'sendMessage'));
    self::assertSame('https://api.telegram.org/file/bot123:abc/path/to/file', $api->fileUrl('123:abc', 'path/to/file'));
    self::assertFalse($api->isLocal);
  }

  public function testFromBase(): void
  {
    $api = TelegramApiServer::fromBase('http://localhost:8081');
    self::assertSame('http://localhost:8081/bot123/getMe', $api->apiUrl('123', 'getMe'));
  }
}
