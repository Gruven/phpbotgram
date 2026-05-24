<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Exceptions;

use Gruven\PhpBotGram\Methods\TelegramMethod;
use Stringable;

class TelegramApiException extends DetailedPhpBotGramException implements Stringable
{
  protected string $label = 'Telegram server says';

  /**
   * @param TelegramMethod<mixed> $method
   */
  public function __construct(
    public readonly TelegramMethod $method,
    string $message,
  ) {
    parent::__construct($message);
  }

  public function __toString(): string
  {
    // Chain to DetailedPhpBotGramException so the docs URL is appended when set
    // by a subclass (mirrors aiogram's `super().__str__()` in TelegramAPIError).
    return "{$this->label} - " . parent::__toString();
  }
}
