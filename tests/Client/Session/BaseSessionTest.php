<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client\Session;

use Gruven\PhpBotGram\Exceptions\TelegramBadRequestException;
use Gruven\PhpBotGram\Exceptions\TelegramRetryAfter;
use Gruven\PhpBotGram\Methods\SendMessage;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use PHPUnit\Framework\TestCase;

final class BaseSessionTest extends TestCase
{
  public function testCheckResponseMapsRetryAfter(): void
  {
    $bot = new MockedBot();
    $session = $bot->getMockedSession();
    $method = new SendMessage(chatId: 1, text: 'x');

    $this->expectException(TelegramRetryAfter::class);
    $session->checkResponse(
      bot: $bot,
      method: $method,
      statusCode: 429,
      content: (string)json_encode(['ok' => false, 'description' => 'flood', 'parameters' => ['retry_after' => 30]]),
    );
  }

  public function testCheckResponseMapsBadRequest(): void
  {
    $bot = new MockedBot();
    $session = $bot->getMockedSession();
    $method = new SendMessage(chatId: 1, text: 'x');

    $this->expectException(TelegramBadRequestException::class);
    $session->checkResponse(
      bot: $bot,
      method: $method,
      statusCode: 400,
      content: (string)json_encode(['ok' => false, 'description' => 'bad chat id']),
    );
  }
}
