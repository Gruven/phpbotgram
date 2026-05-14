<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Methods\GetMe;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Types\User;
use Gruven\PhpBotGram\Utils\DeepLinking;
use Gruven\PhpBotGram\Utils\DeepLinkType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use WeakMap;

/**
 * Unit tests for {@see DeepLinking}.
 *
 * Port of upstream `tests/test_utils/test_deep_linking.py` equivalents.
 *
 * Upstream skips
 * --------------
 * - `test_custom_encode_decode` uses PyCryptodome AES — depends on a Python
 *   third-party library with no PHP equivalent in this port; the round-trip
 *   concept is covered by `PayloadTest::testRoundTripWithCustomEncoderDecoder`
 *   — test infrastructure divergence (c).
 *
 * Phase 7 fix
 * -----------
 * Cache migrated from `array<int, string>` (keyed by `spl_object_id`) to
 * `WeakMap<Bot, string>`. GC eviction is a stdlib invariant; the test below
 * verifies via `gc_collect_cycles()` that the entry disappears after the
 * Bot variable goes out of scope.
 */
final class DeepLinkingTest extends TestCase
{
  // ---------------------------------------------------------------------------
  // createDeepLink — basic
  // ---------------------------------------------------------------------------

  public function testCreateDeepLinkStart(): void
  {
    self::assertSame(
      'https://t.me/mybot?start=foo',
      DeepLinking::createDeepLink('mybot', DeepLinkType::Start, 'foo'),
    );
  }

  public function testCreateDeepLinkStartGroup(): void
  {
    self::assertSame(
      'https://t.me/mybot?startgroup=foo',
      DeepLinking::createDeepLink('mybot', DeepLinkType::StartGroup, 'foo'),
    );
  }

  public function testCreateDeepLinkWithAppName(): void
  {
    self::assertSame(
      'https://t.me/mybot/myapp?startapp=foo',
      DeepLinking::createDeepLink('mybot', DeepLinkType::StartApp, 'foo', appName: 'myapp'),
    );
  }

  // ---------------------------------------------------------------------------
  // createDeepLink — validation
  // ---------------------------------------------------------------------------

  public function testCreateDeepLinkThrowsOnBadChars(): void
  {
    $this->expectException(InvalidArgumentException::class);
    DeepLinking::createDeepLink('mybot', DeepLinkType::Start, 'bad payload!');
  }

  public function testCreateDeepLinkThrowsOnSpace(): void
  {
    $this->expectException(InvalidArgumentException::class);
    DeepLinking::createDeepLink('mybot', DeepLinkType::Start, 'bad chars here');
  }

  public function testCreateDeepLinkThrowsOnTooLongPayload(): void
  {
    $this->expectException(InvalidArgumentException::class);
    DeepLinking::createDeepLink('mybot', DeepLinkType::Start, str_repeat('a', 65));
  }

  public function testCreateDeepLinkExactly64CharsAllowed(): void
  {
    $payload = str_repeat('a', 64);
    $url = DeepLinking::createDeepLink('mybot', DeepLinkType::Start, $payload);
    self::assertStringContainsString($payload, $url);
  }

  public function testCreateDeepLinkWithEncodeTrueAllowsSpecialChars(): void
  {
    // Spaces and other special chars must pass through when $encode=true because
    // the payload is base64-encoded before the regex check.
    $url = DeepLinking::createDeepLink(
      'mybot',
      DeepLinkType::Start,
      'hello world!',
      encode: true,
    );
    // The URL must contain the encoded payload (base64url, no spaces).
    self::assertStringStartsWith('https://t.me/mybot?start=', $url);
    self::assertStringNotContainsString(' ', $url);
  }

  // ---------------------------------------------------------------------------
  // createStartLink / createStartGroupLink / createStartAppLink
  // ---------------------------------------------------------------------------

  private function makeBot(string $username = 'tbot'): MockedBot
  {
    $bot = new MockedBot();
    $user = new User(id: 42, isBot: true, firstName: 'Bot', username: $username);
    $bot->addResultFor(GetMe::class, ok: true, result: $user);

    return $bot;
  }

  protected function setUp(): void
  {
    // Reset the WeakMap to null before each test so tests are isolated.
    // The first getUsername() call lazily allocates a fresh WeakMap.
    $cache = new ReflectionProperty(DeepLinking::class, 'usernameCache');
    $cache->setValue(null, null);
  }

  public function testCreateStartLinkUsesMe(): void
  {
    $url = DeepLinking::createStartLink($this->makeBot(), 'abc123');
    self::assertSame('https://t.me/tbot?start=abc123', $url);
  }

  public function testCreateStartGroupLinkUsesMe(): void
  {
    $url = DeepLinking::createStartGroupLink($this->makeBot(), 'grp');
    self::assertSame('https://t.me/tbot?startgroup=grp', $url);
  }

  public function testCreateStartAppLinkNoAppName(): void
  {
    $url = DeepLinking::createStartAppLink($this->makeBot(), 'myPayload');
    self::assertSame('https://t.me/tbot?startapp=myPayload', $url);
  }

  public function testCreateStartAppLinkWithAppName(): void
  {
    $url = DeepLinking::createStartAppLink($this->makeBot(), 'myPayload', appName: 'myapp');
    self::assertSame('https://t.me/tbot/myapp?startapp=myPayload', $url);
  }

  public function testCreateStartLinkWithEncodeTrue(): void
  {
    $url = DeepLinking::createStartLink($this->makeBot(), 'hello world!', encode: true);
    self::assertStringStartsWith('https://t.me/tbot?start=', $url);
    self::assertStringNotContainsString(' ', $url);
    self::assertStringNotContainsString('!', $url);
  }

  public function testCreateStartLinkWithCustomEncoder(): void
  {
    $encoder = static fn(string $bytes): string => strrev($bytes);
    $url = DeepLinking::createStartLink($this->makeBot(), 'foo', encoder: $encoder);
    self::assertStringStartsWith('https://t.me/tbot?start=', $url);
  }

  public function testCreateStartAppLinkWithEncodingTrue(): void
  {
    // Mirrors upstream test_get_startapp_link_with_encoding.
    $url = DeepLinking::createStartAppLink($this->makeBot(), 'hello world!', encode: true);
    self::assertStringStartsWith('https://t.me/tbot?startapp=', $url);
    self::assertStringNotContainsString(' ', $url);
    self::assertStringNotContainsString('!', $url);
  }

  public function testCreateStartAppLinkWithAppNameAndEncoding(): void
  {
    // Mirrors upstream test_get_startapp_link_with_app_name_and_encoding.
    $url = DeepLinking::createStartAppLink(
      $this->makeBot(),
      'hello world!',
      encode: true,
      appName: 'myapp',
    );
    self::assertStringStartsWith('https://t.me/tbot/myapp?startapp=', $url);
    self::assertStringNotContainsString(' ', $url);
    self::assertStringNotContainsString('!', $url);
  }

  // ---------------------------------------------------------------------------
  // username caching
  // ---------------------------------------------------------------------------

  public function testCreateStartLinkUsesCachedUsernameOnSecondCall(): void
  {
    // The bot is set up with exactly ONE canned GetMe response.
    // A second createStartLink call would fail with "No canned responses left"
    // if getMe() were invoked again — the cache must prevent that.
    $bot = $this->makeBot('cachedbot');
    // First call: fetches and caches.
    $url1 = DeepLinking::createStartLink($bot, 'foo');
    self::assertSame('https://t.me/cachedbot?start=foo', $url1);

    // Second call: uses cache — no second GetMe request.
    $url2 = DeepLinking::createStartLink($bot, 'bar');
    self::assertSame('https://t.me/cachedbot?start=bar', $url2);
  }

  public function testCacheIsPerBotInstance(): void
  {
    // Two separate Bot instances each get their own GetMe response.
    $bot1 = $this->makeBot('botone');
    $user2 = new User(id: 99, isBot: true, firstName: 'Bot2', username: 'bottwo');
    $bot2 = new MockedBot();
    $bot2->addResultFor(GetMe::class, ok: true, result: $user2);

    $url1 = DeepLinking::createStartLink($bot1, 'x');
    $url2 = DeepLinking::createStartLink($bot2, 'x');

    self::assertSame('https://t.me/botone?start=x', $url1);
    self::assertSame('https://t.me/bottwo?start=x', $url2);
  }

  public function testWeakMapEntryEvictedAfterBotIsGarbageCollected(): void
  {
    // WeakMap eviction on GC is a PHP stdlib invariant; this test confirms that
    // the cache entry disappears once the Bot object is collected so there is no
    // stale-username hazard in long-running daemons.
    $bot = $this->makeBot('gcbot');
    DeepLinking::createStartLink($bot, 'payload');

    // Verify the entry exists before GC.
    $prop = new ReflectionProperty(DeepLinking::class, 'usernameCache');

    /** @var null|WeakMap<Bot, string> $map */
    $map = $prop->getValue(null);
    self::assertNotNull($map);
    self::assertCount(1, $map);
    self::assertTrue(isset($map[$bot]));

    // Drop the only strong reference and force a GC cycle.
    unset($bot);
    gc_collect_cycles();

    // The WeakMap itself is still alive but must now be empty.
    /** @var null|WeakMap<Bot, string> $mapAfterGc */
    $mapAfterGc = $prop->getValue(null);
    self::assertNotNull($mapAfterGc);
    self::assertCount(0, $mapAfterGc);
  }
}
