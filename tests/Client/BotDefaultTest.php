<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client;

use Gruven\PhpBotGram\Client\BotDefault;
use PHPUnit\Framework\TestCase;

final class BotDefaultTest extends TestCase
{
    public function testStoresName(): void
    {
        $d = new BotDefault('parse_mode');
        self::assertSame('parse_mode', $d->name);
    }

    public function testEqualsByName(): void
    {
        $a = new BotDefault('parse_mode');
        $b = new BotDefault('parse_mode');
        $c = new BotDefault('protect_content');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
        self::assertFalse($a === $b, 'Different instances are not identity-equal');
    }

    public function testJsonSerializeThrows(): void
    {
        $d = new BotDefault('parse_mode');
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('parse_mode');
        json_encode($d, JSON_THROW_ON_ERROR);
    }
}
