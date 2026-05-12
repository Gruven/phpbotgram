<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Exceptions;

use Gruven\PhpBotGram\Methods\TelegramMethod;

final class TelegramMigrateToChat extends TelegramApiException
{
  /**
   * @param TelegramMethod<mixed> $method
   */
  public function __construct(
    TelegramMethod $method,
    string $message,
    public readonly int $migrateToChatId,
  ) {
    parent::__construct($method, "The group has been migrated to a supergroup with id {$migrateToChatId}\nOriginal description: {$message}");
  }
}
