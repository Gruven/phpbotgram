<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\Link;

use Gruven\PhpBotGram\Utils\Link\Link;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see Link}.
 *
 * Port of upstream `tests/test_utils/test_link.py` equivalents.
 *
 * Upstream skips
 * --------------
 * - `TestCreateChannelBotLink::test_without_params`,
 *   `TestCreateChannelBotLink::test_parameter`,
 *   `TestCreateChannelBotLink::test_permissions`:
 *   `create_channel_bot_link()` is not ported to PHP — phase scope
 *   deferral (b); that helper is an aiogram convenience that combines
 *   admin-rights flags into a query string and is out of Phase 7 scope.
 *
 * @internal
 */
final class LinkTest extends TestCase
{
  // ---------------------------------------------------------------------------
  // createTelegramLink
  // ---------------------------------------------------------------------------

  public function testCreateTelegramLinkWithPathAndQuery(): void
  {
    self::assertSame(
      'https://t.me/user?start=abc',
      Link::createTelegramLink(['user'], ['start' => 'abc']),
    );
  }

  public function testCreateTelegramLinkPathOnly(): void
  {
    self::assertSame('https://t.me/mybot', Link::createTelegramLink(['mybot']));
  }

  public function testCreateTelegramLinkMultiplePathSegments(): void
  {
    self::assertSame('https://t.me/mybot/myapp', Link::createTelegramLink(['mybot', 'myapp']));
  }

  public function testCreateTelegramLinkMultipleQueryParams(): void
  {
    $url = Link::createTelegramLink(['bot'], ['a' => '1', 'b' => '2']);
    self::assertStringContainsString('a=1', $url);
    self::assertStringContainsString('b=2', $url);
    self::assertStringStartsWith('https://t.me/bot?', $url);
  }

  public function testCreateTelegramLinkNoArgs(): void
  {
    self::assertSame('https://t.me', Link::createTelegramLink());
  }

  // ---------------------------------------------------------------------------
  // createTgLink
  // ---------------------------------------------------------------------------

  public function testCreateTgLinkWithQuery(): void
  {
    self::assertSame('tg://user?id=42', Link::createTgLink('user', ['id' => '42']));
  }

  public function testCreateTgLinkNoQuery(): void
  {
    self::assertSame('tg://resolve', Link::createTgLink('resolve'));
  }

  // ---------------------------------------------------------------------------
  // docsUrl
  // ---------------------------------------------------------------------------

  public function testDocsUrlWithPathAndFragment(): void
  {
    $url = Link::docsUrl(['handlers'], fragment: 'section');
    self::assertStringStartsWith('https://docs.aiogram.dev/', $url);
    self::assertStringEndsWith('#section', $url);
    self::assertStringContainsString('handlers', $url);
  }

  public function testDocsUrlWithQuery(): void
  {
    $url = Link::docsUrl(['search'], query: ['q' => 'router']);
    self::assertStringContainsString('q=router', $url);
    self::assertStringContainsString('search', $url);
  }

  public function testDocsUrlBasePath(): void
  {
    $url = Link::docsUrl();
    self::assertSame('https://docs.aiogram.dev/en/dev-3.x/', $url);
  }
}
