<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client;

use Gruven\PhpBotGram\Client\TelegramApiServer;
use PHPUnit\Framework\TestCase;

final class TelegramApiServerTest extends TestCase
{
    public function testProductionUrls(): void
    {
        $api = TelegramApiServer::production();
        self::assertSame('https://api.telegram.org/bot123:abc/sendMessage', $api->apiUrl('123:abc', 'sendMessage'));
        self::assertSame('https://api.telegram.org/file/bot123:abc/path/to/file', $api->fileUrl('123:abc', 'path/to/file'));
        self::assertFalse($api->isLocal);
    }

    public function testFromBase(): void
    {
        $api = TelegramApiServer::fromBase('http://localhost:8081');
        self::assertSame('http://localhost:8081/bot123/getMe', $api->apiUrl('123', 'getMe'));
    }
}
