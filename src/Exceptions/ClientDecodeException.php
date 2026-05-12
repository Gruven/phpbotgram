<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Exceptions;

use Throwable;

final class ClientDecodeException extends PhpBotGramException
{
  public readonly string $rawMessage;
  public readonly Throwable $original;
  public readonly mixed $data;

  public function __construct(
    string $message,
    Throwable $original,
    mixed $data,
  ) {
    $origType = $original::class;
    // print_r recurses unbounded on circular refs and dumps the full structure;
    // truncate to a sensible ceiling so a malformed multi-megabyte payload doesn't
    // poison logs and stack traces.
    $dump = print_r($data, true);

    if (strlen($dump) > self::MAX_DUMP) {
      $dump = substr($dump, 0, self::MAX_DUMP) . '… (truncated)';
    }
    parent::__construct("{$message}\nCaused by: {$origType}: {$original->getMessage()}\nContent: {$dump}");
    $this->rawMessage = $message;
    $this->original = $original;
    $this->data = $data;
  }

  private const int MAX_DUMP = 4096;
}
