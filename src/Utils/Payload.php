<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils;

use Closure;
use InvalidArgumentException;

/**
 * URL-safe base64 payload helpers.
 *
 * Port of upstream `aiogram/utils/payload.py` — `encode_payload` / `decode_payload`.
 *
 * PHP does not provide a single-arg URL-safe base64 function, so we apply a
 * character map over the standard `base64_encode` / `base64_decode`:
 *   `+` → `-`, `/` → `_` (encode direction)
 *   `-` → `+`, `_` → `/` (decode direction)
 * Trailing `=` padding characters are stripped on encode and restored on decode.
 */
final class Payload
{
  private function __construct() {}

  /**
   * Encode a string as URL-safe base64 (no padding).
   *
   * @param null|Closure(string): string $encoder optional pre-encoding step applied to
   *                                              the raw UTF-8 bytes before base64
   */
  public static function encode(string $payload, ?Closure $encoder = null): string
  {
    $bytes = $payload;

    if ($encoder !== null) {
      $bytes = $encoder($bytes);
    }

    $b64 = base64_encode($bytes);

    // Make URL-safe and strip padding.
    return rtrim(strtr($b64, ['+' => '-', '/' => '_']), '=');
  }

  /**
   * Decode a URL-safe base64 string (padding is restored automatically).
   *
   * @param null|Closure(string): string $decoder optional post-decoding step applied to
   *                                              the raw bytes after base64 decoding
   */
  public static function decode(string $payload, ?Closure $decoder = null): string
  {
    // Restore URL-safe chars to standard base64.
    $b64 = strtr($payload, ['-' => '+', '_' => '/']);
    // Pad to a multiple of 4.
    $remainder = strlen($b64) % 4;

    if ($remainder !== 0) {
      $b64 .= str_repeat('=', 4 - $remainder);
    }

    $raw = base64_decode($b64, strict: true);

    if ($raw === false) {
      throw new InvalidArgumentException("Invalid base64 payload: '{$payload}'");
    }

    if ($decoder !== null) {
      return $decoder($raw);
    }

    return $raw;
  }
}
