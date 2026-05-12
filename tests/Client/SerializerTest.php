<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Tests\Support\MockedSession;
use Gruven\PhpBotGram\Types\Unspecified;
use Gruven\PhpBotGram\Types\User;
use PHPUnit\Framework\TestCase;

final class SerializerTest extends TestCase
{
  public function testDumpStripsUnspecified(): void
  {
    $user = new User(id: 1, isBot: false, firstName: 'A', lastName: Unspecified::instance());
    $dumped = Serializer::dump($user);
    self::assertArrayNotHasKey('last_name', $dumped);
    self::assertSame(1, $dumped['id']);
    self::assertFalse($dumped['is_bot']);
  }

  public function testDumpPreservesNulls(): void
  {
    $user = new User(id: 1, isBot: false, firstName: 'A', lastName: null);
    $dumped = Serializer::dump($user);
    self::assertArrayHasKey('last_name', $dumped);
    self::assertNull($dumped['last_name']);
  }

  public function testLoadConstructsTypeWithBot(): void
  {
    $bot = new Bot(token: '1:test', session: new MockedSession());
    $user = Serializer::load(User::class, ['id' => 5, 'is_bot' => true, 'first_name' => 'B'], $bot);
    self::assertSame(5, $user->id);
    self::assertSame($bot, $user->bot);
  }
}
