<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Exceptions;

use Gruven\PhpBotGram\Methods\TelegramMethod;

final class TelegramRetryAfter extends TelegramApiException
{
    public function __construct(
        TelegramMethod $method,
        string $message,
        public readonly int $retryAfter,
    ) {
        $methodName = (new \ReflectionClass($method))->getShortName();
        $description = "Flood control exceeded on method '{$methodName}'. Retry in {$retryAfter} seconds.\nOriginal description: {$message}";
        parent::__construct($method, $description);
    }
}
