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
    $bot->addResultFor(
      SendMessage::class,
      ok: true,
      result: new Message(
        messageId: 1,
        messageThreadId: null,
        directMessagesTopic: null,
        fromUser: null,
        senderChat: null,
        senderBoostCount: null,
        senderBusinessBot: null,
        senderTag: null,
        date: new DateTime('@0'),
        guestQueryId: null,
        businessConnectionId: null,
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
