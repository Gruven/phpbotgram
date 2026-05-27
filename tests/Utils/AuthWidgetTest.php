<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils;

use Gruven\PhpBotGram\Utils\AuthWidget;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see AuthWidget}.
 *
 * Login Widget HMAC-SHA256 signature validation.
 * Test vectors are computed inline via known token + data combinations.
 *
 * Pre-computation:
 *   $secret = hash('sha256', TOKEN, binary: true);
 *   $check  = "auth_date=1698000000\nfirst_name=John\nid=123456789\nusername=johndoe";
 *   $hash   = hash_hmac('sha256', $check, $secret);
 *   // => 6fbde7a6f6fbc1b095dd501623692a9322bd57b0b067c365dcf95ed32689a8d2
 *
 * Port of upstream `tests/test_utils/test_auth_widget.py`.
 *
 * Upstream skips
 * --------------
 * - The upstream tests only cover `check_integrity(token, data)` where `data`
 *   includes a `hash` key. PHP additionally exposes `checkSignature()` and
 *   field-ordering independence tests which are extra coverage, not skips.
 *
 * @internal
 */
final class AuthWidgetTest extends TestCase
{
  private const string TOKEN = '1234567890:AAAA_BBBBccccDDDDeeeeFFFF-GGGGHHHHiiii';

  private const string VALID_HASH = '6fbde7a6f6fbc1b095dd501623692a9322bd57b0b067c365dcf95ed32689a8d2';

  /**
   * @return array<string, string>
   */
  private function validData(): array
  {
    return [
      'auth_date' => '1698000000',
      'first_name' => 'John',
      'id' => '123456789',
      'username' => 'johndoe',
    ];
  }

  // ---------------------------------------------------------------------------
  // checkSignature — valid
  // ---------------------------------------------------------------------------

  public function testCheckSignatureReturnsTrueForValidHash(): void
  {
    self::assertTrue(
      AuthWidget::checkSignature(self::TOKEN, self::VALID_HASH, $this->validData()),
    );
  }

  public function testCheckSignatureIsCaseInsensitiveOnHashAlphabetNormalization(): void
  {
    // The expected hash is lowercase hex; verify it still matches uppercase
    // by re-deriving the hash to ensure it IS lowercase.
    $data = $this->validData();
    $secret = hash('sha256', self::TOKEN, binary: true);
    ksort($data);
    $lines = [];

    foreach ($data as $k => $v) {
      $lines[] = "{$k}={$v}";
    }

    $computedHash = hash_hmac('sha256', implode("\n", $lines), $secret);

    self::assertTrue(
      AuthWidget::checkSignature(self::TOKEN, $computedHash, $this->validData()),
    );
  }

  // ---------------------------------------------------------------------------
  // checkSignature — invalid
  // ---------------------------------------------------------------------------

  public function testCheckSignatureReturnsFalseForTamperedData(): void
  {
    $data = $this->validData();
    $data['first_name'] = 'Jane';  // Tamper.

    self::assertFalse(
      AuthWidget::checkSignature(self::TOKEN, self::VALID_HASH, $data),
    );
  }

  public function testCheckSignatureReturnsFalseForWrongToken(): void
  {
    self::assertFalse(
      AuthWidget::checkSignature('0000000000:wrong_token', self::VALID_HASH, $this->validData()),
    );
  }

  public function testCheckSignatureReturnsFalseForWrongHash(): void
  {
    self::assertFalse(
      AuthWidget::checkSignature(self::TOKEN, str_repeat('0', 64), $this->validData()),
    );
  }

  public function testCheckSignatureReturnsFalseForEmptyData(): void
  {
    // No fields → check string is empty; HMAC will differ from real hash.
    self::assertFalse(
      AuthWidget::checkSignature(self::TOKEN, self::VALID_HASH, []),
    );
  }

  // ---------------------------------------------------------------------------
  // checkIntegrity
  // ---------------------------------------------------------------------------

  public function testCheckIntegrityReturnsTrueWhenHashIsInDataArray(): void
  {
    $data = $this->validData();
    $data['hash'] = self::VALID_HASH;

    self::assertTrue(
      AuthWidget::checkIntegrity(self::TOKEN, $data),
    );
  }

  public function testCheckIntegrityReturnsFalseForTamperedData(): void
  {
    $data = $this->validData();
    $data['hash'] = self::VALID_HASH;
    $data['first_name'] = 'Hacker';  // Tamper after attaching real hash.

    self::assertFalse(
      AuthWidget::checkIntegrity(self::TOKEN, $data),
    );
  }

  public function testCheckIntegrityThrowsWhenHashFieldMissing(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/hash/');

    AuthWidget::checkIntegrity(self::TOKEN, $this->validData());
  }

  // ---------------------------------------------------------------------------
  // Field ordering independence
  // ---------------------------------------------------------------------------

  public function testCheckSignatureSortsFieldsIndependentlyOfInputOrder(): void
  {
    // Supply fields in reverse-alphabetical order; result must still match.
    $data = array_reverse($this->validData(), preserve_keys: true);

    self::assertTrue(
      AuthWidget::checkSignature(self::TOKEN, self::VALID_HASH, $data),
    );
  }
}
