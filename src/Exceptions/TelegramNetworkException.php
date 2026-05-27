<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Exceptions;

class TelegramNetworkException extends TelegramApiException
{
  protected string $label = 'HTTP Client says';
}
