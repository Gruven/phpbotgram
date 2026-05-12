<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Types;

use Gruven\PhpBotGram\Types\TelegramObject;
use PHPUnit\Framework\TestCase;

final class TelegramObjectTest extends TestCase
{
    public function testTelegramObjectIsNotReadonlyClass(): void
    {
        $reflection = new \ReflectionClass(TelegramObject::class);
        self::assertFalse($reflection->isReadOnly(), 'TelegramObject must not be `readonly class` so MutableTelegramObject can subclass it');
    }

    public function testTelegramObjectIsAbstract(): void
    {
        $reflection = new \ReflectionClass(TelegramObject::class);
        self::assertTrue($reflection->isAbstract());
    }
}
