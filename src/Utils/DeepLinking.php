<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils;

use Closure;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Utils\Link\Link;
use InvalidArgumentException;

/**
 * Telegram deep-linking helpers.
 *
 * Port of upstream `aiogram/utils/deep_linking.py`.
 *
 * Deviation from upstream: `createDeepLink` accepts a `DeepLinkType` enum
 * instead of an arbitrary `string $linkType`, providing compile-time safety.
 * The upstream Python API uses an `await bot.me()` coroutine; this PHP port
 * calls `Bot::me()` which is synchronous.
 */
final class DeepLinking
{
  /**
   * Maximum allowed payload length (bytes / characters — ASCII printable only).
   */
  private const PAYLOAD_MAX_LENGTH = 64;

  /**
   * Pattern that matches characters NOT allowed in a raw (un-encoded) payload.
   */
  private const BAD_PATTERN = '/[^A-Za-z0-9_-]/';

  private function __construct() {}

  /**
   * Create a `https://t.me/<bot>?start=<payload>` link.
   *
   * @param null|Closure(string): string $encoder optional payload encoder (implies $encode=true)
   */
  public static function createStartLink(
    Bot $bot,
    string $payload,
    bool $encode = false,
    ?Closure $encoder = null,
  ): string {
    $username = $bot->getMe()->username;

    if ($username === null) {
      throw new InvalidArgumentException('Bot has no username; cannot build a deep link.');
    }

    return self::createDeepLink(
      username: $username,
      linkType: DeepLinkType::Start,
      payload: $payload,
      encode: $encode,
      encoder: $encoder,
    );
  }

  /**
   * Create a `https://t.me/<bot>?startgroup=<payload>` link.
   *
   * @param null|Closure(string): string $encoder optional payload encoder
   */
  public static function createStartGroupLink(
    Bot $bot,
    string $payload,
    bool $encode = false,
    ?Closure $encoder = null,
  ): string {
    $username = $bot->getMe()->username;

    if ($username === null) {
      throw new InvalidArgumentException('Bot has no username; cannot build a deep link.');
    }

    return self::createDeepLink(
      username: $username,
      linkType: DeepLinkType::StartGroup,
      payload: $payload,
      encode: $encode,
      encoder: $encoder,
    );
  }

  /**
   * Create a `https://t.me/<bot>/<appName>?startapp=<payload>` link.
   *
   * @param null|Closure(string): string $encoder optional payload encoder
   */
  public static function createStartAppLink(
    Bot $bot,
    string $payload,
    bool $encode = false,
    ?string $appName = null,
    ?Closure $encoder = null,
  ): string {
    $username = $bot->getMe()->username;

    if ($username === null) {
      throw new InvalidArgumentException('Bot has no username; cannot build a deep link.');
    }

    return self::createDeepLink(
      username: $username,
      linkType: DeepLinkType::StartApp,
      payload: $payload,
      appName: $appName,
      encode: $encode,
      encoder: $encoder,
    );
  }

  /**
   * Build a Telegram deep link for the given bot username and link type.
   *
   * @param null|Closure(string): string $encoder optional payload encoder
   *
   * @throws InvalidArgumentException if the payload contains disallowed characters or exceeds 64 chars
   */
  public static function createDeepLink(
    string $username,
    DeepLinkType $linkType,
    string $payload,
    ?string $appName = null,
    bool $encode = false,
    ?Closure $encoder = null,
  ): string {
    if ($encode || $encoder !== null) {
      $payload = Payload::encode($payload, $encoder);
    }

    if (preg_match(self::BAD_PATTERN, $payload) === 1) {
      throw new InvalidArgumentException(
        "Payload '{$payload}' contains characters not allowed in a Telegram deep link. "
        . 'Use $encode=true to base64-encode arbitrary payloads.',
      );
    }

    if (strlen($payload) > self::PAYLOAD_MAX_LENGTH) {
      throw new InvalidArgumentException(
        sprintf(
          'Payload is %d characters long; the maximum allowed length is %d.',
          strlen($payload),
          self::PAYLOAD_MAX_LENGTH,
        ),
      );
    }

    if ($appName === null) {
      return Link::createTelegramLink([$username], [$linkType->value => $payload]);
    }

    return Link::createTelegramLink([$username, $appName], [$linkType->value => $payload]);
  }
}
