<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotContextController;
use Gruven\PhpBotGram\Tests\Support\MockedSession;
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
    $bot = new Bot(token: '1:test', session: new MockedSession());
    $clone = $original->withBot($bot);

    self::assertNotSame($original, $clone);
    self::assertNull($original->bot);
    self::assertSame($bot, $clone->bot);
  }

  public function testAsIsAliasOfWithBot(): void
  {
    $obj = new class extends BotContextController {};
    $bot = new Bot(token: '1:test', session: new MockedSession());
    self::assertSame($obj->withBot($bot)->bot, $obj->as_($bot)->bot);
  }

  public function testWithBotRebindsNestedControllers(): void
  {
    $bot = new Bot(token: '1:test', session: new MockedSession());
    $inner = new class extends BotContextController {};
    $outer = new class ($inner) extends BotContextController {
      public function __construct(public readonly BotContextController $child)
      {
        parent::__construct();
      }
    };

    $rebound = $outer->withBot($bot);

    self::assertSame($bot, $rebound->bot);
    self::assertSame($bot, $rebound->child->bot);
    self::assertNull($outer->bot, 'original outer bot must remain null');
    self::assertNull($outer->child->bot, 'original inner bot must remain null');
  }

  public function testWithBotWalksNestedArrays(): void
  {
    $bot = new Bot(token: '1:test', session: new MockedSession());
    $leaf = new class extends BotContextController {};
    // list<list<BotContextController>> — mirrors InlineKeyboardMarkup::inlineKeyboard.
    $owner = new class ([[$leaf, $leaf], [$leaf]]) extends BotContextController {
      /** @param list<list<BotContextController>> $matrix */
      public function __construct(public readonly array $matrix)
      {
        parent::__construct();
      }
    };

    $rebound = $owner->withBot($bot);

    self::assertSame($bot, $rebound->bot);
    self::assertCount(2, $rebound->matrix);

    foreach ($rebound->matrix as $row) {
      foreach ($row as $cell) {
        self::assertSame($bot, $cell->bot);
      }
    }

    foreach ($owner->matrix as $row) {
      foreach ($row as $cell) {
        self::assertNull($cell->bot);
      }
    }
  }

  public function testWithBotWalksArraysOfControllers(): void
  {
    $bot = new Bot(token: '1:test', session: new MockedSession());
    $leaf = new class extends BotContextController {};
    $owner = new class ([$leaf, $leaf]) extends BotContextController {
      /** @param list<BotContextController> $items */
      public function __construct(public readonly array $items)
      {
        parent::__construct();
      }
    };

    $rebound = $owner->withBot($bot);

    self::assertSame($bot, $rebound->bot);
    self::assertCount(2, $rebound->items);

    foreach ($rebound->items as $item) {
      self::assertSame($bot, $item->bot);
    }

    foreach ($owner->items as $item) {
      self::assertNull($item->bot, 'original array elements must remain null');
    }
  }
}
