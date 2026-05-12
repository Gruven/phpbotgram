<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client;

final readonly class TelegramApiServer
{
    public function __construct(
        public string $base,
        public string $file,
        public bool $isLocal = false,
    ) {}

    public static function production(): self
    {
        return new self(
            base: 'https://api.telegram.org/bot{token}/{method}',
            file: 'https://api.telegram.org/file/bot{token}/{path}',
        );
    }

    public static function test(): self
    {
        return new self(
            base: 'https://api.telegram.org/bot{token}/test/{method}',
            file: 'https://api.telegram.org/file/bot{token}/test/{path}',
        );
    }

    public static function fromBase(string $base, bool $isLocal = false): self
    {
        $base = rtrim($base, '/');
        return new self(
            base: "{$base}/bot{token}/{method}",
            file: "{$base}/file/bot{token}/{path}",
            isLocal: $isLocal,
        );
    }

    public function apiUrl(string $token, string $method): string
    {
        return strtr($this->base, ['{token}' => $token, '{method}' => $method]);
    }

    public function fileUrl(string $token, string $path): string
    {
        return strtr($this->file, ['{token}' => $token, '{path}' => $path]);
    }
}
