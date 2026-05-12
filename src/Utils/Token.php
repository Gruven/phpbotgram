<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils;

use Gruven\PhpBotGram\Exceptions\PhpBotGramException;

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
      throw new PhpBotGramException("Invalid token format: '{$token}'");
    }
    [$left, $right] = explode(':', $token, 2);

    if (!ctype_digit($left) || $right === '') {
      throw new PhpBotGramException("Invalid token format: '{$token}'");
    }
  }

  public static function extractBotId(string $token): int
  {
    self::validate($token);
    [$left] = explode(':', $token, 2);

    return (int)$left;
  }
}
