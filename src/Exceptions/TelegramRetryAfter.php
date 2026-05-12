<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Exceptions;

use Gruven\PhpBotGram\Methods\TelegramMethod;
use ReflectionClass;

final class TelegramRetryAfter extends TelegramApiException
{
  /**
   * @param TelegramMethod<mixed> $method
   */
  public function __construct(
    TelegramMethod $method,
    string $message,
    public readonly int $retryAfter,
  ) {
    $this->url = 'https://core.telegram.org/bots/faq#my-bot-is-hitting-limits-how-do-i-avoid-this';
    $methodName = (new ReflectionClass($method))->getShortName();
    // property_exists works on public/promoted slots without Reflection's accessibility quirks.
    $chatId = property_exists($method, 'chatId') ? $method->chatId : null;
    $context = is_scalar($chatId) ? " in chat {$chatId}" : '';
    $description = "Flood control exceeded on method '{$methodName}'{$context}. Retry in {$retryAfter} seconds.\nOriginal description: {$message}";
    parent::__construct($method, $description);
  }
}
