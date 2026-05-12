<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher\Event;

final class Bases
{
    public const string UNHANDLED = '__phpbotgram_unhandled__';
    public const string REJECTED = '__phpbotgram_rejected__';

    public static function skip(?string $message = null): never
    {
        throw new SkipHandlerException($message ?? 'Handler skipped');
    }
}
