<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client\Session;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Methods\TelegramMethod;

abstract class BaseSession
{
  /** No-op constructor — replaced by Task 1.3 with the full ?TelegramApiServer/$timeout signature. Lets future subclasses chain parent::__construct() without fatal errors. */
  public function __construct() {}

  /** @param TelegramMethod<mixed> $method */
  abstract public function makeRequest(Bot $bot, TelegramMethod $method, ?int $timeout = null): mixed;
  abstract public function close(): void;
}
