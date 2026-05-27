<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\WebApp;

use InvalidArgumentException;
use JsonException;

use function explode;
use function hash_equals;
use function hash_hmac;
use function implode;
use function is_array;
use function is_string;
use function json_decode;
use function ksort;
use function str_starts_with;
use function strpos;
use function substr;
use function urldecode;

use const JSON_THROW_ON_ERROR;

/**
 * HMAC-SHA256-based standard WebApp signature validation and init data parsing.
 *
 * Port of upstream `aiogram/utils/web_app.py`.
 *
 * This variant uses the bot token to derive the HMAC secret and verify the
 * `hash` field in the init data. Use this on the bot server where the token
 * is available.
 */
final class WebApp
{
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
   * Verify the HMAC-SHA256 signature of WebApp init data.
   *
   * The algorithm:
   * 1. Parse the query string.
   * 2. Remove `hash` (the signature to verify) and `signature` (Ed25519).
   * 3. Sort remaining fields alphabetically.
   * 4. Build data-check string as "key=value" pairs joined by newlines.
   * 5. Derive HMAC key: HMAC-SHA256("WebAppData", token).
   * 6. Compute HMAC-SHA256(secret, data_check_string).
   * 7. Compare with the received hash using constant-time comparison.
   *
   * @param string $token the bot token (e.g. "123456:ABC-DEF...")
   * @param string $initData the raw WebApp init data query string
   *
   * @return bool true if the signature is valid, false otherwise
   */
  public static function checkSignature(string $token, string $initData): bool
  {
    /** @var array<string, string> $parsed */
    $parsed = self::parseQuery($initData);

    $hashReceived = $parsed['hash'] ?? null;

    if (!is_string($hashReceived) || $hashReceived === '') {
      return false;
    }

    unset($parsed['hash'], $parsed['signature']);

    ksort($parsed);

    $lines = [];

    foreach ($parsed as $key => $value) {
      $lines[] = $key . '=' . $value;
    }

    $dataCheckString = implode("\n", $lines);

    // Derive the HMAC key from the bot token.
    $secret = hash_hmac('sha256', $token, 'WebAppData', binary: true);
    $hmacHex = hash_hmac('sha256', $dataCheckString, $secret);

    return hash_equals($hmacHex, $hashReceived);
  }

  /**
   * Parse a WebApp init data query string into a {@see WebAppInitData} DTO.
   *
   * Any value that starts with `[` or `{` is auto-decoded as JSON before DTO
   * construction, mirroring upstream `web_app.py:parse_webapp_init_data`:
   * ```python
   * if value.startswith(('[', '{')):
   *     value = json.loads(value)
   * ```
   * Known structured fields (`user`, `receiver`, `chat`) are additionally
   * converted to typed DTOs.
   *
   * @param string $initData the raw WebApp init data query string
   *
   * @throws JsonException if any JSON-shaped field is malformed
   * @throws InvalidArgumentException if required fields (`auth_date`, `hash`) are missing
   */
  public static function parseInitData(string $initData): WebAppInitData
  {
    /** @var array<string, string> $rawParsed */
    $rawParsed = self::parseQuery($initData);

    // Auto-decode any value that looks like a JSON object or array, mirroring
    // upstream: `if value.startswith(('[', '{')): value = json.loads(value)`.
    // Malformed JSON-shaped values raise JsonException (via JSON_THROW_ON_ERROR).
    // Unknown future fields pass through as decoded arrays; known fields are
    // further converted to typed DTOs below.
    /** @var array<string, mixed> $parsed */
    $parsed = $rawParsed;

    foreach ($rawParsed as $key => $value) {
      if ($value !== '' && (str_starts_with($value, '[') || str_starts_with($value, '{'))) {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($value, associative: true, flags: JSON_THROW_ON_ERROR);
        $parsed[$key] = $decoded;
      }
    }

    if (!isset($parsed['auth_date']) || !is_string($parsed['auth_date'])) {
      throw new InvalidArgumentException("WebApp init data is missing required field 'auth_date'.");
    }

    if (!isset($parsed['hash']) || !is_string($parsed['hash'])) {
      throw new InvalidArgumentException("WebApp init data is missing required field 'hash'.");
    }

    $authDate = $parsed['auth_date'];
    $hash = $parsed['hash'];

    $user = null;

    if (isset($parsed['user']) && is_array($parsed['user'])) {
      /** @var array<string, mixed> $userArr */
      $userArr = $parsed['user'];
      $user = WebAppUser::fromArray($userArr);
    }

    $receiver = null;

    if (isset($parsed['receiver']) && is_array($parsed['receiver'])) {
      /** @var array<string, mixed> $receiverArr */
      $receiverArr = $parsed['receiver'];
      $receiver = WebAppUser::fromArray($receiverArr);
    }

    $chat = null;

    if (isset($parsed['chat']) && is_array($parsed['chat'])) {
      /** @var array<string, mixed> $chatArr */
      $chatArr = $parsed['chat'];
      $chat = WebAppChat::fromArray($chatArr);
    }

    $queryId = isset($parsed['query_id']) && is_string($parsed['query_id']) ? $parsed['query_id'] : null;
    $chatType = isset($parsed['chat_type']) && is_string($parsed['chat_type']) ? $parsed['chat_type'] : null;
    $chatInstance = isset($parsed['chat_instance']) && is_string($parsed['chat_instance']) ? $parsed['chat_instance'] : null;
    $startParam = isset($parsed['start_param']) && is_string($parsed['start_param']) ? $parsed['start_param'] : null;

    $canSendAfter = null;

    if (isset($parsed['can_send_after']) && is_string($parsed['can_send_after'])) {
      $canSendAfter = (int)$parsed['can_send_after'];
    }

    return new WebAppInitData(
      authDate: (int)$authDate,
      hash: $hash,
      queryId: $queryId,
      user: $user,
      receiver: $receiver,
      chat: $chat,
      chatType: $chatType,
      chatInstance: $chatInstance,
      startParam: $startParam,
      canSendAfter: $canSendAfter,
    );
  }

  /**
   * Parse and validate WebApp init data in one step.
   *
   * Calls {@see self::checkSignature()} first and throws if the signature is
   * invalid, then delegates to {@see self::parseInitData()}.
   *
   * @param string $token the bot token
   * @param string $initData the raw WebApp init data query string
   *
   * @throws InvalidArgumentException if the signature is invalid or required fields are missing
   * @throws JsonException if any nested JSON field is malformed
   */
  public static function safeParseInitData(string $token, string $initData): WebAppInitData
  {
    if (!self::checkSignature($token, $initData)) {
      throw new InvalidArgumentException('Invalid WebApp init data signature.');
    }

    return self::parseInitData($initData);
  }
}
