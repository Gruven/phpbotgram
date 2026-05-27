<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\WebApp;

use InvalidArgumentException;
use JsonException;
use SodiumException;

use function explode;
use function extension_loaded;
use function implode;
use function is_string;
use function ksort;
use function sodium_crypto_sign_verify_detached;
use function str_repeat;
use function strlen;
use function strpos;
use function strtr;
use function substr;
use function urldecode;

/**
 * Ed25519-based third-party WebApp signature validation.
 *
 * Port of upstream `aiogram/utils/web_app_signature.py`.
 *
 * This variant does NOT require the bot token — it uses the Telegram
 * Ed25519 public key to verify the `signature` field in the init data.
 * Use this when you need to validate WebApp data outside the bot server
 * (e.g., in a microservice that has no access to the bot secret).
 *
 * Requires the `sodium` PHP extension (built-in since PHP 7.2).
 */
final class WebAppSignature
{
  /**
   * Production Ed25519 public key (hex-encoded).
   */
  public const string PRODUCTION_PUBLIC_KEY_HEX = 'e7bf03a2fa4602af4580703d88dda5bb59f32ed8b02a56c187fe7d34caed242d';

  /**
   * Test/sandbox Ed25519 public key (hex-encoded).
   */
  public const string TEST_PUBLIC_KEY_HEX = '40055058a4ee38156a06562e52eece92a771bcd8346a8c4615cb7376eddf72ec';

  private function __construct() {}

  /**
   * Parse a URL-encoded query string into a string-keyed assoc array,
   * preserving the literal key names (no `.` or space mangling). Mirrors
   * Python's `urllib.parse.parse_qsl(strict_parsing=True)`.
   *
   * @return array<string, string>
   */
  private static function parseQuery(string $input): array
  {
    if ($input === '') {
      return [];
    }

    $result = [];

    foreach (explode('&', $input) as $pair) {
      if ($pair === '') {
        continue;
      }

      $eq = strpos($pair, '=');

      if ($eq === false) {
        // Key without value — treat as empty-string value.
        $result[urldecode($pair)] = '';

        continue;
      }

      $key = urldecode(substr($pair, 0, $eq));
      $value = urldecode(substr($pair, $eq + 1));
      $result[$key] = $value;
    }

    return $result;
  }

  /**
   * Verify and parse WebApp init data using the Ed25519 public key in one step.
   *
   * Calls {@see self::check()} first and throws if the signature is invalid,
   * then delegates to {@see WebApp::parseInitData()}.
   *
   * Port of upstream `safe_check_webapp_init_data_from_signature` in
   * `aiogram/utils/web_app_signature.py`.
   *
   * @param int $botId the numeric bot ID (extracted from the token)
   * @param string $initData the raw WebApp init data query string
   * @param null|string $publicKeyHex hex-encoded Ed25519 public key;
   *                                  defaults to the production key
   *
   * @throws InvalidArgumentException if the signature is invalid
   * @throws JsonException if any nested JSON field is malformed
   */
  public static function safeParseInitData(int $botId, string $initData, ?string $publicKeyHex = null): WebAppInitData
  {
    if (!self::check($botId, $initData, $publicKeyHex)) {
      throw new InvalidArgumentException('Invalid WebApp init data signature.');
    }

    return WebApp::parseInitData($initData);
  }

  /**
   * Verify an Ed25519 signature for WebApp init data.
   *
   * The `signature` field in the init data is a URL-safe base64-encoded
   * Ed25519 signature over:
   *
   *   "{$botId}:WebAppData\n" + sorted key=value pairs (newline-separated)
   *
   * @param int $botId the numeric bot ID (extracted from the token)
   * @param string $initData the raw WebApp init data query string
   * @param null|string $publicKeyHex hex-encoded Ed25519 public key;
   *                                  defaults to the production key
   *
   * @return bool true if the signature is valid, false otherwise
   */
  public static function check(int $botId, string $initData, ?string $publicKeyHex = null): bool
  {
    if (!extension_loaded('sodium')) {
      return false;
    }

    // Parse the query string into an assoc array.
    /** @var array<string, string> $parsed */
    $parsed = self::parseQuery($initData);

    // Extract and remove the signature field.
    $signatureB64 = $parsed['signature'] ?? null;

    if (!is_string($signatureB64) || $signatureB64 === '') {
      return false;
    }

    unset($parsed['signature'], $parsed['hash']);

    // Sort remaining fields alphabetically.
    ksort($parsed);

    // Build the data-check string.
    $lines = [];

    foreach ($parsed as $key => $value) {
      $lines[] = $key . '=' . $value;
    }

    $dataCheckString = "{$botId}:WebAppData\n" . implode("\n", $lines);

    // Decode URL-safe base64 (restore padding).
    $b64 = strtr($signatureB64, ['-' => '+', '_' => '/']);
    $remainder = strlen($b64) % 4;

    if ($remainder !== 0) {
      $b64 .= str_repeat('=', 4 - $remainder);
    }

    $signature = base64_decode($b64, strict: true);

    if ($signature === false || $signature === '') {
      return false;
    }

    // Decode the public key.
    $keyHex = $publicKeyHex ?? self::PRODUCTION_PUBLIC_KEY_HEX;
    $publicKey = hex2bin($keyHex);

    if ($publicKey === false || $publicKey === '') {
      return false;
    }

    try {
      return sodium_crypto_sign_verify_detached($signature, $dataCheckString, $publicKey);
    } catch (SodiumException) {
      // Invalid signature length or key format.
      return false;
    }
  }
}
