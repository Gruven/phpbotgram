<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Support;

use PHPUnit\Framework\TestCase;

final class MockedSessionTest extends TestCase
{
  public function testBotMeReturnsStub(): void
  {
    $bot = new MockedBot();
    self::assertSame(42, $bot->me()->id);
    self::assertSame('tbot', $bot->me()->username);
  }
}
