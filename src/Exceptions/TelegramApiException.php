<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Exceptions;

use Gruven\PhpBotGram\Methods\TelegramMethod;

class TelegramApiException extends DetailedPhpBotGramException
{
    protected string $label = 'Telegram server says';

    public function __construct(
        public readonly TelegramMethod $method,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function __toString(): string
    {
        return "{$this->label} - {$this->detail}";
    }
}
