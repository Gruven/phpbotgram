<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Types\InputFile;

final readonly class Request
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, InputFile>|null $files
     */
    public function __construct(
        public string $method,
        public array $data,
        public ?array $files = null,
    ) {}
}
