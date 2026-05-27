<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils;

use InvalidArgumentException;

use function hash;
use function hash_equals;
use function hash_hmac;
use function implode;
use function ksort;

/**
 * Telegram Login Widget signature validation.
 *
 * Port of upstream `aiogram/utils/auth_widget.py`.
 *
 * When a user authenticates via the Telegram Login Widget, Telegram passes
 * a set of fields (including a `hash`) to your callback URL. These helpers
 * let you verify that the data was signed by the correct bot.
 *
 * Algorithm (per Telegram docs):
 * 1. Compute `secret = SHA-256(bot_token)` — note: raw bytes, NOT hex.
 * 2. Sort the data fields alphabetically (excluding `hash`).
 * 3. Build check string as "key=value" pairs joined by newlines.
 * 4. Compute `HMAC-SHA256(secret, check_string)`.
 * 5. Compare hex digest with the received `hash` using constant-time comparison.
 */
final class AuthWidget
{
  private function __construct() {}

  /**
   * Verify a Telegram Login Widget hash.
   *
   * @param string $token the bot token
   * @param string $hash the hex-encoded HMAC-SHA256 hash received from Telegram
   * @param array<string, int|string> $data the remaining widget data fields (without `hash`)
   *
   * @return bool true if the hash is valid, false otherwise
   */
  public static function checkSignature(string $token, string $hash, array $data): bool
  {
    // Secret is SHA-256 of the raw bot token (binary output).
    $secret = hash('sha256', $token, binary: true);

    // Sort by key and build "key=value\n..." check string.
    ksort($data);

    $lines = [];

    foreach ($data as $key => $value) {
      $lines[] = "{$key}={$value}";
    }

    $checkString = implode("\n", $lines);

    $hmacHex = hash_hmac('sha256', $checkString, $secret);

    return hash_equals($hmacHex, $hash);
  }

  /**
   * Verify Login Widget data integrity when the `hash` is inside the data array.
   *
   * Extracts `hash` from `$data`, removes it, then delegates to
   * {@see self::checkSignature()}.
   *
   * @param string $token the bot token
   * @param array<string, int|string> $data the full widget data including `hash`
   *
   * @return bool true if the hash is valid, false otherwise
   *
   * @throws InvalidArgumentException if `$data` does not contain a `hash` key
   */
  public static function checkIntegrity(string $token, array $data): bool
  {
    if (!isset($data['hash'])) {
      throw new InvalidArgumentException("Login Widget data must contain a 'hash' field.");
    }

    $hash = (string)$data['hash'];
    unset($data['hash']);

    return self::checkSignature($token, $hash, $data);
  }
}
