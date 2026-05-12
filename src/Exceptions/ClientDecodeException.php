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
    parent::__construct("{$message}\nCaused by: {$origType}: {$original->getMessage()}\nContent: " . print_r($data, true));
    $this->rawMessage = $message;
    $this->original = $original;
    $this->data = $data;
  }
}
