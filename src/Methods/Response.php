<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Types\ResponseParameters;

/**
 * @template TResult
 */
final readonly class Response
{
    public function __construct(
        public bool $ok,
        public mixed $result = null,
        public ?string $description = null,
        public ?int $errorCode = null,
        public ?ResponseParameters $parameters = null,
    ) {}
}
