<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Methods;

use Gruven\PhpBotGram\Methods\SetWebhook;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use PHPUnit\Framework\TestCase;

/**
 * Upstream: tests/test_api/test_methods/test_set_webhook.py
 *
 * Upstream skips:
 *   - async test infrastructure — API divergence (a)/test infrastructure
 *     divergence (c).
 *   - Certificate InputFile upload path — API divergence (a): InputFile upload
 *     is covered by InputFileTest.
 *   - Pydantic model_dump — API divergence (a).
 */
final class SetWebhookTest extends TestCase
{
  // ── constructor shape ────────────────────────────────────────────────────────

  public function testRequiredUrlOnly(): void
  {
    $method = new SetWebhook(url: 'https://example.com/hook');
    self::assertSame('https://example.com/hook', $method->url);
    self::assertNull($method->certificate);
    self::assertNull($method->ipAddress);
    self::assertNull($method->maxConnections);
    self::assertNull($method->allowedUpdates);
    self::assertNull($method->dropPendingUpdates);
    self::assertNull($method->secretToken);
  }

  public function testWithAllOptions(): void
  {
    $method = new SetWebhook(
      url: 'https://bot.example.com/webhook',
      ipAddress: '1.2.3.4',
      maxConnections: 40,
      allowedUpdates: ['message'],
      dropPendingUpdates: true,
      secretToken: 'mysecret',
    );
    self::assertSame('https://bot.example.com/webhook', $method->url);
    self::assertSame('1.2.3.4', $method->ipAddress);
    self::assertSame(40, $method->maxConnections);
    self::assertSame(['message'], $method->allowedUpdates);
    self::assertTrue($method->dropPendingUpdates);
    self::assertSame('mysecret', $method->secretToken);
  }

  public function testApiMethodConstant(): void
  {
    self::assertSame('setWebhook', SetWebhook::ApiMethod);
  }

  public function testReturnsTypeBool(): void
  {
    self::assertSame('bool', SetWebhook::ReturnsType);
  }

  // ── MockedBot round-trip ─────────────────────────────────────────────────────

  public function testRoundTripReturnsTrue(): void
  {
    $bot = new MockedBot();
    $bot->addResultFor(SetWebhook::class, ok: true, result: true);

    $result = $bot->setWebhook(url: 'https://example.com/hook');

    self::assertTrue($result);
  }

  public function testGetRequestCapturesUrl(): void
  {
    $bot = new MockedBot();
    $bot->addResultFor(SetWebhook::class, ok: true, result: true);

    $bot->setWebhook(url: 'https://example.com/hook', secretToken: 'tok123');

    $sent = $bot->getRequest();
    self::assertInstanceOf(SetWebhook::class, $sent);
    self::assertSame('https://example.com/hook', $sent->url);
    self::assertSame('tok123', $sent->secretToken);
  }
}
