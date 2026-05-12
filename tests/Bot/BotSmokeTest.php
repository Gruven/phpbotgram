<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Bot;

use Gruven\PhpBotGram\Methods\SendMessage;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\Message;
use PHPUnit\Framework\TestCase;

final class BotSmokeTest extends TestCase
{
  public function testSendMessageRoundtrip(): void
  {
    $bot = new MockedBot();
    // After the Cycle 1 fix to TypeRenderer's constructor ordering, optional
    // properties no longer interleave with required ones in raw schema
    // order, so the user can construct Message with just the required
    // params + whatever optionals they care about — no longer forced to
    // pass `null` positionally for every preceding optional.
    $bot->addResultFor(
      SendMessage::class,
      ok: true,
      result: new Message(
        messageId: 1,
        date: new DateTime('@0'),
        chat: new Chat(id: 42, type: 'private'),
        text: 'hi',
      ),
    );

    $result = $bot->sendMessage(chatId: 42, text: 'hi');

    self::assertInstanceOf(Message::class, $result);
    self::assertSame('hi', $result->text);

    $sent = $bot->getRequest();
    self::assertInstanceOf(SendMessage::class, $sent);
    self::assertSame('hi', $sent->text);
  }
}
