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
    $this->url = 'https://core.telegram.org/bots/api#responseparameters';
    $chatId = property_exists($method, 'chatId') ? $method->chatId : null;
    $from = is_scalar($chatId) ? " from chat {$chatId}" : '';
    parent::__construct($method, "The group has been migrated{$from} to a supergroup with id {$migrateToChatId}\nOriginal description: {$message}");
  }
}
