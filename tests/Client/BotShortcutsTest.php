<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Methods\Response;
use Gruven\PhpBotGram\Tests\Support\MockedSession;
use Gruven\PhpBotGram\Tests\Support\RunAsyncTrait;
use Gruven\PhpBotGram\Types\User;
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
    // Cache hit on the second call — same int, no exception.
    self::assertSame(7890123, $bot->getId());
  }

  public function testGetDefaultPropertiesCachedAcrossCalls(): void
  {
    $bot = new Bot(token: '1:test', session: new MockedSession());
    self::assertSame($bot->getDefaultProperties(), $bot->getDefaultProperties());
  }

  public function testContextInvokesBodyWithBotAndClosesSessionWhenAutoCloseIsTrue(): void
  {
    // `context()` returns a Closure that, when invoked with a body closure,
    // calls the body with `$this` (the bot) and closes the session in a
    // `finally` block. Mirrors aiogram's `async with bot.context() as bot:`.
    $session = new MockedSession();
    $bot = new Bot(token: '1:test', session: $session);
    $received = null;

    $result = ($bot->context())(static function (Bot $b) use (&$received): string {
      $received = $b;

      return 'body-return';
    });

    self::assertSame($bot, $received, 'Body closure must receive the originating bot');
    self::assertSame('body-return', $result, 'context() must propagate body return value');
    self::assertTrue($session->closed, 'Session must be closed when autoClose=true');
  }

  public function testContextSkipsSessionCloseWhenAutoCloseIsFalse(): void
  {
    // Opt-out path: callers managing the session lifecycle themselves
    // pass `autoClose: false` so context() doesn't yank the connection
    // out from under a later call site.
    $session = new MockedSession();
    $bot = new Bot(token: '1:test', session: $session);

    ($bot->context(autoClose: false))(static fn (Bot $b): null => null);

    self::assertFalse($session->closed, 'Session must remain open when autoClose=false');
  }

  public function testMeCachesGetMeResult(): void
  {
    // `me()` runs `GetMe` once and stashes the result; subsequent calls
    // must return the same User instance without re-invoking the API.
    $first = new User(id: 7890123, isBot: true, firstName: 'TestBot');
    $session = new MockedSession();
    $session->addResult(new Response(ok: true, result: $first));
    $bot = new Bot(token: '7890123:hash', session: $session);

    self::assertSame($first, $bot->me());

    // Second call must hit the cache — the session has no queued response,
    // so a re-dispatch would throw. The fact that this assertion completes
    // proves the cache short-circuits before reaching the session.
    self::assertSame($first, $bot->me());
  }
}
