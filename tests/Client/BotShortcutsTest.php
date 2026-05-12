<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Tests\Support\MockedSession;
use Gruven\PhpBotGram\Tests\Support\RunAsyncTrait;
use PHPUnit\Framework\TestCase;

final class BotShortcutsTest extends TestCase
{
  use RunAsyncTrait;

  protected function tearDown(): void
  {
    Bot::resetCurrentBot();
  }

  public function testCurrentReturnsNullWhenNotSet(): void
  {
    Bot::resetCurrentBot();
    self::assertNull(Bot::current());
  }

  public function testSetCurrentRoundtrip(): void
  {
    $bot = new Bot(token: '1:test', session: new MockedSession());
    Bot::setCurrent($bot);
    self::assertSame($bot, Bot::current());

    Bot::setCurrent(null);
    self::assertNull(Bot::current());
  }

  public function testResetCurrentBotClearsFiberLocal(): void
  {
    $bot = new Bot(token: '1:test', session: new MockedSession());
    Bot::setCurrent($bot);
    self::assertSame($bot, Bot::current());

    Bot::resetCurrentBot();
    self::assertNull(Bot::current());
  }

  public function testGetIdParsesTokenPrefix(): void
  {
    $bot = new Bot(token: '7890123:hash', session: new MockedSession());
    self::assertSame(7890123, $bot->getId());
  }
}
