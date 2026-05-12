<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Dispatcher\Event;

use Gruven\PhpBotGram\Dispatcher\Event\Bases;
use Gruven\PhpBotGram\Dispatcher\Event\SkipHandlerException;
use PHPUnit\Framework\TestCase;

final class BasesTest extends TestCase
{
    public function testUnhandledHasStableSentinelValue(): void
    {
        // Pin the actual sentinel string so a rename in Bases::class breaks this test.
        self::assertSame('__phpbotgram_unhandled__', Bases::UNHANDLED);
    }

    public function testRejectedIsDistinctFromUnhandled(): void
    {
        self::assertNotSame(Bases::UNHANDLED, Bases::REJECTED);
    }

    public function testSkipHelperThrowsSkipException(): void
    {
        $this->expectException(SkipHandlerException::class);
        Bases::skip('passing on this update');
    }
}
