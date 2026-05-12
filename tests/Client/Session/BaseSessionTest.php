<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client\Session;

use Gruven\PhpBotGram\Exceptions\TelegramBadRequestException;
use Gruven\PhpBotGram\Exceptions\TelegramRetryAfter;
use Gruven\PhpBotGram\Methods\SendMessage;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Types\Unspecified;
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

  public function testPrepareValueRoundtripsListThroughFormBody(): void
  {
    $bot = new MockedBot();
    $session = $bot->getMockedSession();
    $files = [];

    $prepared = $session->prepareValue([['type' => 'bold', 'offset' => 0, 'length' => 4]], $bot, $files);
    self::assertIsString($prepared, 'lists should be JSON-encoded by prepareValue');

    $encoded = http_build_query(['entities' => $prepared]);
    parse_str($encoded, $decoded);
    self::assertIsString($decoded['entities']);

    /** @var mixed $roundtrip */
    $roundtrip = json_decode($decoded['entities'], associative: true, flags: JSON_THROW_ON_ERROR);
    self::assertSame([['type' => 'bold', 'offset' => 0, 'length' => 4]], $roundtrip);
  }

  public function testPrepareValueStripsUnspecifiedFromNestedDict(): void
  {
    $bot = new MockedBot();
    $session = $bot->getMockedSession();
    $files = [];

    $prepared = $session->prepareValue(
      ['a' => 1, 'b' => Unspecified::instance(), 'c' => 'keep'],
      $bot,
      $files,
    );
    self::assertIsString($prepared);

    /** @var mixed $decoded */
    $decoded = json_decode($prepared, associative: true, flags: JSON_THROW_ON_ERROR);
    self::assertSame(['a' => 1, 'c' => 'keep'], $decoded);
  }
}
