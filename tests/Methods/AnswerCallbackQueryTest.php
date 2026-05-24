<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Methods;

use Gruven\PhpBotGram\Methods\AnswerCallbackQuery;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use PHPUnit\Framework\TestCase;

/**
 * Upstream: tests/test_api/test_methods/test_answer_callback_query.py
 *
 * Upstream skips:
 *   - async infrastructure (pytest-asyncio, AsyncMock) — API divergence (a)/
 *     test infrastructure divergence (c).
 *   - Pydantic model_dump round-trips — API divergence (a): replaced by
 *     constructor-shape verification and MockedBot dispatch.
 *
 * @internal
 */
final class AnswerCallbackQueryTest extends TestCase
{
  // ── constructor shape ────────────────────────────────────────────────────────

  public function testRequiredArgOnly(): void
  {
    $method = new AnswerCallbackQuery(callbackQueryId: 'cq123');
    self::assertSame('cq123', $method->callbackQueryId);
    self::assertNull($method->text);
    self::assertNull($method->showAlert);
    self::assertNull($method->url);
    self::assertNull($method->cacheTime);
  }

  public function testWithAllOptions(): void
  {
    $method = new AnswerCallbackQuery(
      callbackQueryId: 'abc',
      text: 'Done!',
      showAlert: true,
      url: 'https://example.com',
      cacheTime: 5,
    );
    self::assertSame('abc', $method->callbackQueryId);
    self::assertSame('Done!', $method->text);
    self::assertTrue($method->showAlert);
    self::assertSame('https://example.com', $method->url);
    self::assertSame(5, $method->cacheTime);
  }

  public function testApiMethodConstant(): void
  {
    self::assertSame('answerCallbackQuery', AnswerCallbackQuery::ApiMethod);
  }

  public function testReturnsTypeBool(): void
  {
    self::assertSame('bool', AnswerCallbackQuery::ReturnsType);
  }

  // ── MockedBot round-trip ─────────────────────────────────────────────────────

  public function testRoundTripReturnsTrue(): void
  {
    $bot = new MockedBot();
    $bot->addResultFor(AnswerCallbackQuery::class, ok: true, result: true);

    $result = $bot->answerCallbackQuery(callbackQueryId: 'cq_abc', text: 'OK');

    self::assertTrue($result);
  }

  public function testGetRequestCapture(): void
  {
    $bot = new MockedBot();
    $bot->addResultFor(AnswerCallbackQuery::class, ok: true, result: true);

    $bot->answerCallbackQuery(callbackQueryId: 'cq_xyz', showAlert: true);

    $sent = $bot->getRequest();
    self::assertInstanceOf(AnswerCallbackQuery::class, $sent);
    self::assertSame('cq_xyz', $sent->callbackQueryId);
    self::assertTrue($sent->showAlert);
  }
}
