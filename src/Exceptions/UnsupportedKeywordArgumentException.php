<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Exceptions;

final class UnsupportedKeywordArgumentException extends DetailedPhpBotGramException
{
  public function __construct(public readonly string $argName, string $message)
  {
    parent::__construct($message);
  }
}
