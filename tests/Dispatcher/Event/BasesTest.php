<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Dispatcher\Event;

use Gruven\PhpBotGram\Dispatcher\Event\Bases;
use Gruven\PhpBotGram\Dispatcher\Event\RejectedSentinel;
use Gruven\PhpBotGram\Dispatcher\Event\SkipHandlerException;
use Gruven\PhpBotGram\Dispatcher\Event\UnhandledSentinel;
use PHPUnit\Framework\TestCase;

final class BasesTest extends TestCase
{
  public function testUnhandledIsStableSingleton(): void
  {
    self::assertSame(Bases::unhandled(), Bases::unhandled());
    self::assertInstanceOf(UnhandledSentinel::class, Bases::unhandled());
  }

  public function testRejectedIsDistinctFromUnhandled(): void
  {
    self::assertSame(Bases::rejected(), Bases::rejected());
    self::assertInstanceOf(RejectedSentinel::class, Bases::rejected());
    self::assertNotSame(Bases::unhandled(), Bases::rejected());
  }

  public function testSkipHelperThrowsSkipException(): void
  {
    $this->expectException(SkipHandlerException::class);
    Bases::skip('passing on this update');
  }
}
