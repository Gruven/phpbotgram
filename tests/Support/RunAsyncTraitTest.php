<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Support;

use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;

final class RunAsyncTraitTest extends TestCase
{
  use RunAsyncTrait;

  public function testRunAsyncDrivesFuture(): void
  {
    $result = $this->runAsync(static fn(): int => 42);
    self::assertSame(42, $result);
  }

  public function testResetEventLoopProducesFreshDriver(): void
  {
    // PHPUnit's $this->tearDown() does NOT invoke #[After] hooks; we call
    // the reset method directly to verify it produces a fresh driver.
    $driver = EventLoop::getDriver();
    $this->resetEventLoop();
    self::assertNotSame($driver, EventLoop::getDriver());
  }
}
