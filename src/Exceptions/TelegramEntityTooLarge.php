<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Exceptions;

use Gruven\PhpBotGram\Methods\TelegramMethod;

class TelegramEntityTooLarge extends TelegramNetworkException
{
  /**
   * @param TelegramMethod<mixed> $method
   */
  public function __construct(TelegramMethod $method, string $message)
  {
    $this->url = 'https://core.telegram.org/bots/api#sending-files';
    parent::__construct($method, $message);
  }
}
