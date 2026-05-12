<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client\Session;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotDefault;
use Gruven\PhpBotGram\Client\DefaultBotProperties;
use Gruven\PhpBotGram\Exceptions\TelegramBadRequestException;
use Gruven\PhpBotGram\Exceptions\TelegramRetryAfter;
use Gruven\PhpBotGram\Methods\SendMessage;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Tests\Support\MockedSession;
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

  public function testMockedSessionRoutesErrorResponsesThroughCheckResponse(): void
  {
    $bot = new MockedBot();
    $bot->addResultFor(SendMessage::class, ok: false, description: 'chat not found', errorCode: 400);

    $this->expectException(TelegramBadRequestException::class);
    $bot->sendMessage(chatId: 1, text: 'hi');
  }

  public function testMockedSessionMapsRetryAfterFromQueuedResponse(): void
  {
    $bot = new MockedBot();
    $bot->addResultFor(
      SendMessage::class,
      ok: false,
      description: 'Too Many Requests',
      errorCode: 429,
      retryAfter: 5,
    );

    $this->expectException(TelegramRetryAfter::class);
    $bot->sendMessage(chatId: 1, text: 'hi');
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

  public function testPrepareValueResolvesBotDefaultAgainstBotDefaultProperties(): void
  {
    $bot = new Bot(
      token: '1:test',
      session: new MockedSession(),
      defaultProperties: new DefaultBotProperties(parseMode: 'HTML'),
    );
    $session = $bot->session;
    self::assertInstanceOf(MockedSession::class, $session);
    $files = [];

    $prepared = $session->prepareValue(new BotDefault('parse_mode'), $bot, $files, dumpsJson: false);
    self::assertSame('HTML', $prepared);
  }

  public function testPrepareValueDumpsNestedTelegramMethod(): void
  {
    // A TelegramMethod nested inside another method's payload should be
    // dumped via Serializer (not json_encoded directly, which would hit
    // BotDefault::jsonSerialize and throw LogicException).
    $bot = new MockedBot();
    $session = $bot->getMockedSession();
    $files = [];

    $nested = new SendMessage(chatId: 99, text: 'nested', parseMode: null);
    // Wrap the method as a nested value the way a generated method might.
    $prepared = $session->prepareValue($nested, $bot, $files, dumpsJson: false);

    self::assertIsArray($prepared);
    self::assertSame(99, $prepared['chat_id']);
    self::assertSame('nested', $prepared['text']);
  }

  public function testJsonLoadsAndJsonDumpsAreInjectable(): void
  {
    $calls = ['loads' => 0, 'dumps' => 0];
    $session = new MockedSession(
      jsonLoads: static function (string $s) use (&$calls): mixed {
        $calls['loads']++;

        return json_decode($s, associative: true, flags: JSON_THROW_ON_ERROR);
      },
      jsonDumps: static function (mixed $v) use (&$calls): string {
        $calls['dumps']++;

        return (string)json_encode($v, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
      },
    );
    $bot = new Bot(token: '1:test', session: $session);
    $files = [];

    $session->prepareValue([['x' => 1]], $bot, $files);
    self::assertSame(1, $calls['dumps'], 'jsonDumps should fire on list serialisation');

    $session->checkResponse(
      bot: $bot,
      method: new SendMessage(chatId: 1, text: 'x'),
      statusCode: 200,
      content: (string)json_encode(['ok' => true]),
    );
    self::assertSame(1, $calls['loads'], 'jsonLoads should fire on response decode');
  }
}
