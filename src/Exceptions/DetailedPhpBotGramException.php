<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Exceptions;

use Stringable;

class DetailedPhpBotGramException extends PhpBotGramException implements Stringable
{
  /** Subclasses set this in their constructor before the message is rendered. */
  public ?string $url = null;

  public function __construct(public readonly string $detail)
  {
    parent::__construct($detail);
  }

  /**
   * Lazy URL augmentation — mirrors aiogram's `DetailedAiogramError.__str__`.
   * `getMessage()` keeps the raw detail; stringifying the exception appends
   * the documentation URL if the subclass set one.
   */
  public function __toString(): string
  {
    if ($this->url !== null) {
      return "{$this->detail}\n(background on this error at: {$this->url})";
    }

    return $this->detail;
  }
}
