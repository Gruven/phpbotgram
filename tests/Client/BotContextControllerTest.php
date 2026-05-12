<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotContextController;
use PHPUnit\Framework\TestCase;

final class BotContextControllerTest extends TestCase
{
    public function testBotDefaultsToNull(): void
    {
        $obj = new class extends BotContextController {};
        self::assertNull($obj->bot);
    }

    public function testWithBotReturnsClone(): void
    {
        $original = new class extends BotContextController {};
        $bot = new Bot();
        $clone = $original->withBot($bot);

        self::assertNotSame($original, $clone);
        self::assertNull($original->bot);
        self::assertSame($bot, $clone->bot);
    }

    public function testAsIsAliasOfWithBot(): void
    {
        $obj = new class extends BotContextController {};
        $bot = new Bot();
        self::assertEquals($obj->withBot($bot)->bot, $obj->as_($bot)->bot);
    }
}
