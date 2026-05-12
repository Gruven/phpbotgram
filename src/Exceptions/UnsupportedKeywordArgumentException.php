<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Exceptions;

final class UnsupportedKeywordArgumentException extends DetailedPhpBotGramException
{
  public function __construct(public readonly string $argName, string $message)
  {
    $this->url = 'https://docs.aiogram.dev/en/latest/migration_2_to_3.html#unsupported-keyword-arguments';
    parent::__construct($message);
  }
}
