<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client\Session;

use Gruven\PhpBotGram\Client\Session\AmphpSession;
use Gruven\PhpBotGram\Client\TelegramApiServer;
use PHPUnit\Framework\TestCase;

/**
 * Upstream: tests/test_api/test_client/test_session/test_aiohttp_session.py
 *
 * PHP equivalent: AmphpSession (amphp/http-client) vs. Python's AiohttpSession (aiohttp).
 *
 * Upstream skips:
 *   - test_create_session — API divergence (a): aiohttp creates a ClientSession
 *     lazily; AmphpSession creates an Amp HttpClient on demand. Internal session
 *     lifecycle is not part of the public API contract.
 *   - test_create_proxy_session — API divergence (a): aiohttp uses
 *     aiohttp_socks.ProxyConnector; AmphpSession proxy support is handled via
 *     amphp/http-client connector abstraction (different API surface).
 *   - test_create_proxy_session_proxy_url — same as above.
 *   - test_create_proxy_session_chained_proxies — same as above.
 *   - test_reset_connector — API divergence (a): connector-reset is an
 *     aiohttp-specific concept; AmphpSession has no corresponding state.
 *   - test_close_session — API divergence (a): aiohttp.ClientSession.close is
 *     async; AmphpSession::close() is synchronous and covered by integration
 *     tests only when the Amp event loop is running.
 *   - test_build_form_data_with_data_only — API divergence (a): upstream
 *     inspects aiohttp's FormData._fields; PHP builds an array that is passed
 *     to amphp/http-client's BodyForm. The wire result is equivalent but the
 *     internal representation is incompatible.
 *   - test_build_form_data_with_files — same as above.
 *   - test_make_request — API divergence (a): requires a live aresponses HTTP
 *     mock server and aiohttp; PHP equivalent needs a live Amp event loop. The
 *     dispatch path is covered by BotSmokeTest via MockedSession.
 *   - test_make_request_network_error — API divergence (a): aiohttp raises
 *     aiohttp.ClientError / asyncio.TimeoutError; AmphpSession wraps
 *     Amp\Http\Client\HttpException as TelegramNetworkException.
 *   - test_stream_content — API divergence (a): returns AsyncGenerator; PHP
 *     returns Amp ReadableStream. Structural coverage via BotDownloadTest.
 *   - test_stream_content_404 — same as above.
 *   - test_context_manager — API divergence (a): Python `async with session`
 *     is an async context manager; PHP has no equivalent pattern.
 *
 * @internal
 */
final class AmphpSessionTest extends TestCase
{
  /** AmphpSession defaults to PRODUCTION API server. */
  public function testDefaultApiIsProduction(): void
  {
    $session = new AmphpSession();
    $production = TelegramApiServer::production();
    self::assertSame($production->base, $session->api->base);
    self::assertFalse($session->api->isLocal);
  }

  /** AmphpSession accepts a custom API server at construction. */
  public function testCustomApiServerIsStored(): void
  {
    $localApi = TelegramApiServer::fromBase('http://localhost:8081', isLocal: true);
    $session = new AmphpSession(api: $localApi);
    self::assertSame($localApi, $session->api);
    self::assertTrue($session->api->isLocal);
  }

  /** AmphpSession middleware manager starts empty. */
  public function testMiddlewareManagerStartsEmpty(): void
  {
    $session = new AmphpSession();
    self::assertCount(0, $session->middleware);
  }
}
