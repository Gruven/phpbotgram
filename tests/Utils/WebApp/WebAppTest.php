<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\WebApp;

use Gruven\PhpBotGram\Utils\WebApp\WebApp;
use Gruven\PhpBotGram\Utils\WebApp\WebAppInitData;
use Gruven\PhpBotGram\Utils\WebApp\WebAppUser;
use InvalidArgumentException;
use JsonException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function hash_hmac;
use function implode;
use function ksort;

/**
 * Unit tests for {@see WebApp}.
 *
 * HMAC-SHA256 signature validation and init data parsing.
 * Test vectors are computed inline so there are no external dependencies.
 *
 * Port of upstream `tests/test_utils/test_web_app.py`.
 *
 * Upstream skips
 * --------------
 * - Row 2 of `test_check_webapp_signature` (user JSON with surrogate-pair
 *   characters `🇺🇦`): Python's `parse_qsl(strict_parsing=True)`
 *   rejects malformed pairs; PHP `parse_str` is lenient. The PHP port returns
 *   `false` for the `"test&foo=bar=baz"` case by verifying HMAC mismatch
 *   (not by strict-parsing rejection) — API divergence (a).
 * - `test_parse_web_app_init_data` date check (`parsed.auth_date.year == 2022`):
 *   PHP stores `authDate` as `int` epoch, not a `datetime` object — API
 *   divergence (a); equivalent via `(new \DateTime())->setTimestamp($data->authDate)->format('Y')`.
 *
 * @internal
 */
final class WebAppTest extends TestCase
{
  /**
   * Bot token used across all HMAC tests.
   */
  private const string TOKEN = '1234567890:AAAA_BBBBccccDDDDeeeeFFFF-GGGGHHHHiiii';

  /**
   * Pre-computed HMAC hash for TOKEN + sample init data fields.
   *
   * Generated via:
   *   $secret = hash_hmac('sha256', TOKEN, 'WebAppData', binary: true);
   *   $hash   = hash_hmac('sha256', "auth_date=1698000000\nquery_id=AABCDE\nuser={\"id\":123,...}", $secret);
   */
  private const string VALID_HASH = 'ff803ca9d811a3a90cf6c7ed347ccba00322402128af49ce706a2bea540e4eb1';

  /**
   * Build a valid init data query string (without the hash, for re-computation).
   *
   * @return array{initData: string, hash: string}
   */
  private function makeValidInitData(): array
  {
    $params = [
      'auth_date' => '1698000000',
      'query_id' => 'AABCDE',
      'user' => '{"id":123,"first_name":"John"}',
    ];
    $params['hash'] = self::VALID_HASH;

    return [
      'initData' => http_build_query($params),
      'hash' => self::VALID_HASH,
    ];
  }

  // ---------------------------------------------------------------------------
  // checkSignature — valid
  // ---------------------------------------------------------------------------

  public function testCheckSignatureReturnsTrueForValidHmac(): void
  {
    $fixture = $this->makeValidInitData();

    self::assertTrue(
      WebApp::checkSignature(self::TOKEN, $fixture['initData']),
    );
  }

  // ---------------------------------------------------------------------------
  // checkSignature — invalid
  // ---------------------------------------------------------------------------

  public function testCheckSignatureReturnsFalseForTamperedData(): void
  {
    $fixture = $this->makeValidInitData();
    $tampered = str_replace('auth_date=1698000000', 'auth_date=9999999999', $fixture['initData']);

    self::assertFalse(
      WebApp::checkSignature(self::TOKEN, $tampered),
    );
  }

  public function testCheckSignatureReturnsFalseForWrongToken(): void
  {
    $fixture = $this->makeValidInitData();

    self::assertFalse(
      WebApp::checkSignature('0000000000:wrong_token_here', $fixture['initData']),
    );
  }

  public function testCheckSignatureReturnsFalseForMissingHash(): void
  {
    // Build init data without a hash field.
    $params = [
      'auth_date' => '1698000000',
      'query_id' => 'AABCDE',
    ];

    self::assertFalse(
      WebApp::checkSignature(self::TOKEN, http_build_query($params)),
    );
  }

  public function testCheckSignatureReturnsFalseForEmptyInitData(): void
  {
    self::assertFalse(
      WebApp::checkSignature(self::TOKEN, ''),
    );
  }

  // ---------------------------------------------------------------------------
  // parseInitData
  // ---------------------------------------------------------------------------

  public function testParseInitDataReturnsWebAppInitData(): void
  {
    $fixture = $this->makeValidInitData();
    $data = WebApp::parseInitData($fixture['initData']);

    self::assertInstanceOf(WebAppInitData::class, $data);
    self::assertSame(1698000000, $data->authDate);
    self::assertSame(self::VALID_HASH, $data->hash);
    self::assertSame('AABCDE', $data->queryId);
    self::assertNotNull($data->user);
    self::assertSame(123, $data->user->id);
    self::assertSame('John', $data->user->firstName);
  }

  public function testParseInitDataThrowsOnMissingAuthDate(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/auth_date/');

    WebApp::parseInitData('hash=abcdef');
  }

  public function testParseInitDataThrowsOnMissingHash(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/hash/');

    WebApp::parseInitData('auth_date=1698000000');
  }

  public function testParseInitDataThrowsOnMalformedUserJson(): void
  {
    $this->expectException(JsonException::class);

    $params = [
      'auth_date' => '1698000000',
      'hash' => 'aabbcc',
      'user' => '{not valid json}',
    ];

    WebApp::parseInitData(http_build_query($params));
  }

  public function testParseInitDataHandlesChatField(): void
  {
    $params = [
      'auth_date' => '1698000000',
      'hash' => 'aabbcc',
      'chat' => '{"id":9876,"type":"supergroup","title":"Test Chat"}',
    ];

    $data = WebApp::parseInitData(http_build_query($params));

    self::assertNotNull($data->chat);
    self::assertSame(9876, $data->chat->id);
    self::assertSame('supergroup', $data->chat->type);
    self::assertSame('Test Chat', $data->chat->title);
  }

  public function testParseInitDataHandlesOptionalFields(): void
  {
    $params = [
      'auth_date' => '1698000000',
      'hash' => 'aabbcc',
      'chat_type' => 'group',
      'chat_instance' => '-12345',
      'start_param' => 'deeplink',
      'can_send_after' => '60',
    ];

    $data = WebApp::parseInitData(http_build_query($params));

    self::assertSame('group', $data->chatType);
    self::assertSame('-12345', $data->chatInstance);
    self::assertSame('deeplink', $data->startParam);
    self::assertSame(60, $data->canSendAfter);
  }

  // ---------------------------------------------------------------------------
  // WebAppUser — isBot nullable
  // ---------------------------------------------------------------------------

  public function testWebAppUserIsBotPreservesNullWhenAbsent(): void
  {
    // When 'is_bot' is absent from the array, isBot must be null (not false).
    $user = WebAppUser::fromArray(['id' => 1, 'first_name' => 'X']);
    self::assertNull($user->isBot);
  }

  public function testWebAppUserIsBotTrueWhenPresent(): void
  {
    $user = WebAppUser::fromArray(['id' => 1, 'first_name' => 'X', 'is_bot' => true]);
    self::assertTrue($user->isBot);
  }

  public function testWebAppUserIsBotFalseWhenPresentFalse(): void
  {
    $user = WebAppUser::fromArray(['id' => 1, 'first_name' => 'X', 'is_bot' => false]);
    self::assertFalse($user->isBot);
  }

  // ---------------------------------------------------------------------------
  // parseQuery — key fidelity (no parse_str mangling)
  // ---------------------------------------------------------------------------

  public function testParseQueryPreservesKeyWithDot(): void
  {
    $reflection = new ReflectionClass(WebApp::class);
    $method = $reflection->getMethod('parseQuery');

    /** @var array<string, string> $result */
    $result = $method->invoke(null, 'a.b=1');
    self::assertSame(['a.b' => '1'], $result);
  }

  public function testParseQueryPreservesKeyWithSpace(): void
  {
    $reflection = new ReflectionClass(WebApp::class);
    $method = $reflection->getMethod('parseQuery');

    /** @var array<string, string> $result */
    $result = $method->invoke(null, 'a+b=1');
    self::assertSame(['a b' => '1'], $result);
  }

  public function testParseQueryHandlesEmptyInput(): void
  {
    $reflection = new ReflectionClass(WebApp::class);
    $method = $reflection->getMethod('parseQuery');

    /** @var array<string, string> $result */
    $result = $method->invoke(null, '');
    self::assertSame([], $result);
  }

  public function testParseQueryHandlesKeyWithoutEquals(): void
  {
    $reflection = new ReflectionClass(WebApp::class);
    $method = $reflection->getMethod('parseQuery');

    /** @var array<string, string> $result */
    $result = $method->invoke(null, 'lone_key');
    self::assertSame(['lone_key' => ''], $result);
  }

  public function testCheckSignatureHandlesKeyWithDot(): void
  {
    // Build init data with a dotted key and compute the expected HMAC.
    $token = self::TOKEN;
    $params = [
      'a.b' => '1',
      'auth_date' => '1698000000',
    ];
    // Sort alphabetically (a.b < auth_date).
    ksort($params);
    $lines = [];

    foreach ($params as $k => $v) {
      $lines[] = "{$k}={$v}";
    }

    $dataCheckString = implode("\n", $lines);
    $secret = hash_hmac('sha256', $token, 'WebAppData', binary: true);
    $hash = hash_hmac('sha256', $dataCheckString, $secret);

    // Build the raw query string manually to preserve the dot in the key.
    $initData = 'a.b=1&auth_date=1698000000&hash=' . $hash;

    self::assertTrue(WebApp::checkSignature($token, $initData));
  }

  // ---------------------------------------------------------------------------
  // parseInitData — auto-decode unknown JSON-shaped fields
  // ---------------------------------------------------------------------------

  public function testParseInitDataAutoDecodesUnknownStructuredField(): void
  {
    // Supply a mystery field whose value is a URL-encoded JSON object.
    // The auto-decode loop must decode it without crashing, even though
    // WebAppInitData has no property for "mystery".
    $params = [
      'auth_date' => '1698000000',
      'hash' => 'aabbcc',
      'mystery' => '{"foo":"1"}',
    ];

    // Should not throw — unknown keys with JSON values are silently decoded
    // in the pipeline and ignored during DTO construction.
    $data = WebApp::parseInitData(http_build_query($params));

    self::assertInstanceOf(WebAppInitData::class, $data);
    self::assertSame(1698000000, $data->authDate);
  }

  public function testParseInitDataThrowsOnMalformedUnknownJsonField(): void
  {
    $this->expectException(JsonException::class);

    $params = [
      'auth_date' => '1698000000',
      'hash' => 'aabbcc',
      'mystery' => '{not valid json}',
    ];

    WebApp::parseInitData(http_build_query($params));
  }

  // ---------------------------------------------------------------------------
  // safeParseInitData
  // ---------------------------------------------------------------------------

  public function testSafeParseInitDataReturnsDtoForValidSignature(): void
  {
    $fixture = $this->makeValidInitData();
    $data = WebApp::safeParseInitData(self::TOKEN, $fixture['initData']);

    self::assertInstanceOf(WebAppInitData::class, $data);
    self::assertSame(1698000000, $data->authDate);
  }

  public function testSafeParseInitDataThrowsForInvalidSignature(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/signature/i');

    $params = [
      'auth_date' => '1698000000',
      'hash' => 'bad_hash_value',
    ];

    WebApp::safeParseInitData(self::TOKEN, http_build_query($params));
  }
}
