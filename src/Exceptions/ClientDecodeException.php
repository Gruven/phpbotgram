<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Exceptions;

final class ClientDecodeException extends PhpBotGramException
{
    public function __construct(
        public readonly string $message,
        public readonly \Throwable $original,
        public readonly mixed $data,
    ) {
        $origType = $original::class;
        parent::__construct("{$message}\nCaused by: {$origType}: {$original->getMessage()}\nContent: " . print_r($data, true));
    }
}
