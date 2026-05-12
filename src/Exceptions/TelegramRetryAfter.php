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
    $reflect = new ReflectionClass($method);
    $methodName = $reflect->getShortName();
    $chatId = $reflect->hasProperty('chatId') ? $reflect->getProperty('chatId')->getValue($method) : null;
    $context = is_scalar($chatId) ? " (chat_id={$chatId})" : '';
    $description = "Flood control exceeded on method '{$methodName}'{$context}. Retry in {$retryAfter} seconds.\nOriginal description: {$message}";
    parent::__construct($method, $description);
  }
}
