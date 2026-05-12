<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Exceptions;

use Gruven\PhpBotGram\Exceptions\PhpBotGramException;
use Gruven\PhpBotGram\Exceptions\TelegramApiException;
use Gruven\PhpBotGram\Exceptions\TelegramBadRequestException;
use Gruven\PhpBotGram\Exceptions\TelegramMigrateToChat;
use Gruven\PhpBotGram\Exceptions\TelegramRetryAfter;
use Gruven\PhpBotGram\Methods\TelegramMethod;
use PHPUnit\Framework\TestCase;

final class ExceptionHierarchyTest extends TestCase
{
    public function testApiInheritsFromBase(): void
    {
        self::assertTrue(is_subclass_of(TelegramApiException::class, PhpBotGramException::class));
    }

    public function testRetryAfterCarriesPayload(): void
    {
        $method = $this->anonymousMethod();
        $e = new TelegramRetryAfter($method, 'Flood control', retryAfter: 30);
        self::assertSame(30, $e->retryAfter);
        self::assertSame($method, $e->method);
    }

    public function testMigrateToChatPayload(): void
    {
        $method = $this->anonymousMethod();
        $e = new TelegramMigrateToChat($method, 'Migrated', migrateToChatId: -100123);
        self::assertSame(-100123, $e->migrateToChatId);
    }

    public function testBadRequestInheritsFromApiException(): void
    {
        self::assertTrue(is_subclass_of(TelegramBadRequestException::class, TelegramApiException::class));
    }

    private function anonymousMethod(): TelegramMethod
    {
        return new class extends TelegramMethod {
            public const string ApiMethod = 'x';
            public const string ReturnsType = '';
        };
    }
}
