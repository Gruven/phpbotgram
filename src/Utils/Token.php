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

    // We empirically observe BotFather to emit only `[A-Za-z0-9_-]` after the
    // colon — Telegram does not publicly document this guarantee. The regex is
    // pinned because `TelegramApiServer::apiUrl`/`fileUrl` concatenate the token
    // directly into the URL path (`/bot{token}/{method}`); without the
    // restriction a `/` or `..` in the right half would inject into the path.
    // If Telegram ever introduces a new character in tokens, this regex must
    // be relaxed AND the URL builder updated to URL-encode the right half.
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
