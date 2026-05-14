<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\WebApp;

use function base64_encode;
use function extension_loaded;

use Gruven\PhpBotGram\Utils\WebApp\WebAppInitData;
use Gruven\PhpBotGram\Utils\WebApp\WebAppSignature;

use function implode;

use InvalidArgumentException;

use function ksort;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function rtrim;
use function strtr;

/**
 * Unit tests for {@see WebAppSignature}.
 *
 * Ed25519 signature validation for third-party WebApp init data verification.
 *
 * All test vectors are generated via sodium_crypto_sign_keypair() /
 * sodium_crypto_sign_detached() to avoid external network dependencies.
 *
 * Port of upstream `tests/test_utils/test_web_app_signature.py`.
 *
 * Upstream skips
 * --------------
 * - `test_safe_check_webapp_init_data_from_signature` (Python):
 *   calls `safe_check_webapp_init_data_from_signature()` which returns a
 *   `WebAppInitData` with a `.hash` field from the data — the PHP port has
 *   no `safeCheckWebAppInitDataFromSignature` helper; this functionality is
 *   covered by the combination of `WebAppSignature::check()` and
 *   `WebApp::parseInitData()` — API divergence (a).
 * - Upstream uses hardcoded hex private/public key bytes; PHP generates
 *   a fresh keypair per test via `sodium_crypto_sign_keypair()` to avoid
 *   hardcoded secret material — test infrastructure divergence (c).
 */
final class WebAppSignatureTest extends TestCase
{
  /**
   * Derive a deterministic keypair and a signed init data string for testing.
   *
   * @return array{publicKeyHex: string, initData: string, botId: int}
   */
  private function makeFixture(): array
  {
    $botId = 1234567890;

    $keypair = sodium_crypto_sign_keypair();
    $privateKey = sodium_crypto_sign_secretkey($keypair);
    $publicKey = sodium_crypto_sign_publickey($keypair);

    $params = [
      'auth_date' => '1698000000',
      'query_id' => 'AABCDE',
      'user' => '{"id":123,"first_name":"John"}',
    ];

    ksort($params);

    $lines = [];

    foreach ($params as $key => $value) {
      $lines[] = "{$key}={$value}";
    }

    $dataCheckString = "{$botId}:WebAppData\n" . implode("\n", $lines);
    $signature = sodium_crypto_sign_detached($dataCheckString, $privateKey);
    $signatureB64 = rtrim(strtr(base64_encode($signature), ['+' => '-', '/' => '_']), '=');

    $params['signature'] = $signatureB64;

    return [
      'publicKeyHex' => bin2hex($publicKey),
      'initData' => http_build_query($params),
      'botId' => $botId,
    ];
  }

  // ---------------------------------------------------------------------------
  // Valid signature
  // ---------------------------------------------------------------------------

  public function testCheckReturnsTrueForValidSignature(): void
  {
    $fixture = $this->makeFixture();

    self::assertTrue(
      WebAppSignature::check(
        $fixture['botId'],
        $fixture['initData'],
        $fixture['publicKeyHex'],
      ),
    );
  }

  public function testCheckAcceptsCustomPublicKey(): void
  {
    $fixture = $this->makeFixture();

    // The custom key is the generated test key — should pass.
    self::assertTrue(
      WebAppSignature::check(
        $fixture['botId'],
        $fixture['initData'],
        publicKeyHex: $fixture['publicKeyHex'],
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // Invalid / tampered data
  // ---------------------------------------------------------------------------

  public function testCheckReturnsFalseForWrongPublicKey(): void
  {
    $fixture = $this->makeFixture();

    // A different valid keypair — public key mismatch.
    $otherKeypair = sodium_crypto_sign_keypair();
    $otherPublicKeyHex = bin2hex(sodium_crypto_sign_publickey($otherKeypair));

    self::assertFalse(
      WebAppSignature::check(
        $fixture['botId'],
        $fixture['initData'],
        $otherPublicKeyHex,
      ),
    );
  }

  public function testCheckReturnsFalseForTamperedData(): void
  {
    $fixture = $this->makeFixture();

    // Tamper with a field in the init data.
    $tampered = str_replace('auth_date=1698000000', 'auth_date=9999999999', $fixture['initData']);

    self::assertFalse(
      WebAppSignature::check(
        $fixture['botId'],
        $tampered,
        $fixture['publicKeyHex'],
      ),
    );
  }

  public function testCheckReturnsFalseForWrongBotId(): void
  {
    $fixture = $this->makeFixture();

    // Correct init data but wrong bot ID changes the data-check string.
    self::assertFalse(
      WebAppSignature::check(
        999999999,
        $fixture['initData'],
        $fixture['publicKeyHex'],
      ),
    );
  }

  public function testCheckReturnsFalseWhenSignatureFieldMissing(): void
  {
    $fixture = $this->makeFixture();

    // Remove the signature parameter from the query string.
    $noSig = preg_replace('/&?signature=[^&]+/', '', $fixture['initData']);

    self::assertFalse(
      WebAppSignature::check(
        $fixture['botId'],
        (string)$noSig,
        $fixture['publicKeyHex'],
      ),
    );
  }

  public function testCheckReturnsFalseForEmptyInitData(): void
  {
    self::assertFalse(
      WebAppSignature::check(
        1234567890,
        '',
        WebAppSignature::TEST_PUBLIC_KEY_HEX,
      ),
    );
  }

  public function testCheckReturnsFalseForGarbageSignature(): void
  {
    $fixture = $this->makeFixture();

    // Replace signature value with garbage.
    $badSig = preg_replace('/signature=[^&]+/', 'signature=AAAAAAAAAAAAAAAA', $fixture['initData']);

    self::assertFalse(
      WebAppSignature::check(
        $fixture['botId'],
        (string)$badSig,
        $fixture['publicKeyHex'],
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // parseQuery — key fidelity (no parse_str mangling)
  // ---------------------------------------------------------------------------

  public function testParseQueryPreservesKeyWithDot(): void
  {
    $reflection = new ReflectionClass(WebAppSignature::class);
    $method = $reflection->getMethod('parseQuery');

    /** @var array<string, string> $result */
    $result = $method->invoke(null, 'a.b=1');
    self::assertSame(['a.b' => '1'], $result);
  }

  public function testCheckHandlesKeyWithDot(): void
  {
    if (!extension_loaded('sodium')) {
      self::markTestSkipped('sodium extension not available');
    }

    $botId = 1234567890;
    $keypair = sodium_crypto_sign_keypair();
    $privateKey = sodium_crypto_sign_secretkey($keypair);
    $publicKey = sodium_crypto_sign_publickey($keypair);

    $params = [
      'a.b' => '1',
      'auth_date' => '1698000000',
    ];
    ksort($params);
    $lines = [];

    foreach ($params as $k => $v) {
      $lines[] = "{$k}={$v}";
    }

    $dataCheckString = "{$botId}:WebAppData\n" . implode("\n", $lines);
    $signature = sodium_crypto_sign_detached($dataCheckString, $privateKey);
    $signatureB64 = rtrim(strtr(base64_encode($signature), ['+' => '-', '/' => '_']), '=');

    // Build raw query string preserving the dot.
    $initData = 'a.b=1&auth_date=1698000000&signature=' . $signatureB64;

    self::assertTrue(
      WebAppSignature::check($botId, $initData, bin2hex($publicKey)),
    );
  }

  // ---------------------------------------------------------------------------
  // Public key constants
  // ---------------------------------------------------------------------------

  public function testProductionPublicKeyHexIsCorrectLength(): void
  {
    // Ed25519 public key is 32 bytes = 64 hex chars.
    self::assertSame(64, strlen(WebAppSignature::PRODUCTION_PUBLIC_KEY_HEX));
  }

  public function testTestPublicKeyHexIsCorrectLength(): void
  {
    self::assertSame(64, strlen(WebAppSignature::TEST_PUBLIC_KEY_HEX));
  }

  public function testProductionPublicKeyMatchesUpstream(): void
  {
    self::assertSame(
      'e7bf03a2fa4602af4580703d88dda5bb59f32ed8b02a56c187fe7d34caed242d',
      WebAppSignature::PRODUCTION_PUBLIC_KEY_HEX,
    );
  }

  public function testTestPublicKeyMatchesUpstream(): void
  {
    self::assertSame(
      '40055058a4ee38156a06562e52eece92a771bcd8346a8c4615cb7376eddf72ec',
      WebAppSignature::TEST_PUBLIC_KEY_HEX,
    );
  }

  // ---------------------------------------------------------------------------
  // safeParseInitData
  // ---------------------------------------------------------------------------

  public function testSafeParseInitDataReturnsDtoOnValidSignature(): void
  {
    $fixture = $this->makeFixture();

    // parseInitData requires a 'hash' field (HMAC variant); include a placeholder
    // so the DTO can be constructed. The Ed25519 path does not validate hash here.
    $initDataWithHash = $fixture['initData'] . '&hash=placeholder';

    $data = WebAppSignature::safeParseInitData(
      $fixture['botId'],
      $initDataWithHash,
      $fixture['publicKeyHex'],
    );

    self::assertInstanceOf(WebAppInitData::class, $data);
    self::assertSame(1698000000, $data->authDate);
    self::assertSame('AABCDE', $data->queryId);
  }

  public function testSafeParseInitDataThrowsOnInvalidSignature(): void
  {
    $fixture = $this->makeFixture();

    // Tamper with the init data so the signature no longer matches.
    $tampered = str_replace('auth_date=1698000000', 'auth_date=9999999999', $fixture['initData']);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/signature/i');

    WebAppSignature::safeParseInitData(
      $fixture['botId'],
      $tampered,
      $fixture['publicKeyHex'],
    );
  }
}
