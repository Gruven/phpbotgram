<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils;

use Gruven\PhpBotGram\Exceptions\TokenValidationException;

final class Token
{
  private function __construct() {}

  public static function validate(string $token): void
  {
    if (
      $token === ''
      || !str_contains($token, ':')
      || preg_match('/\s/', $token) === 1
    ) {
      throw new TokenValidationException("Invalid token format: '{$token}'");
    }
    [$left, $right] = explode(':', $token, 2);

    // Telegram-issued bot tokens use only `[A-Za-z0-9_-]` after the colon —
    // pinning the regex blocks path-traversal style payloads from slash-injecting
    // into URLs constructed by TelegramApiServer::apiUrl.
    if (!ctype_digit($left) || $right === '' || preg_match('/^[A-Za-z0-9_-]+$/', $right) !== 1) {
      throw new TokenValidationException("Invalid token format: '{$token}'");
    }
  }

  public static function extractBotId(string $token): int
  {
    self::validate($token);
    [$left] = explode(':', $token, 2);

    return (int)$left;
  }
}
