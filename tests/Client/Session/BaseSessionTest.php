<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client\Session;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotDefault;
use Gruven\PhpBotGram\Client\DefaultBotProperties;
use Gruven\PhpBotGram\Exceptions\ClientDecodeException;
use Gruven\PhpBotGram\Exceptions\TelegramBadRequestException;
use Gruven\PhpBotGram\Exceptions\TelegramRetryAfter;
use Gruven\PhpBotGram\Methods\DeleteMessage;
use Gruven\PhpBotGram\Methods\EditMessageText;
use Gruven\PhpBotGram\Methods\GetUpdates;
use Gruven\PhpBotGram\Methods\SendMessage;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Tests\Support\MockedSession;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\Unspecified;
use Gruven\PhpBotGram\Types\Update;
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

  public function testPrepareValueElidesBotDefaultWhenPropertyMissing(): void
  {
    // The spec's null-filter rule: a BotDefault that resolves to null disappears
    // from the wire entirely. Reason this regression test exists: without
    // null-elision, an empty `parse_mode` would reach Telegram as `parse_mode=`
    // and the API would reject the request.
    $bot = new Bot(
      token: '1:test',
      session: new MockedSession(),
      defaultProperties: new DefaultBotProperties(), // no parse_mode set
    );
    $session = $bot->session;
    self::assertInstanceOf(MockedSession::class, $session);
    $files = [];

    $prepared = $session->prepareValue(new BotDefault('parse_mode'), $bot, $files, dumpsJson: false);
    self::assertNull($prepared, 'unresolved BotDefault must produce null so AmphpSession::buildFormBody drops the field');
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

  /**
   * Cycle 3 review fix: BaseSession::buildResponse deserialises a 2xx
   * success payload through Serializer::load against the Method's
   * declared `ReturnsType`. The previous Phase 1 stub hard-coded
   * `result: null`, forcing AmphpSession to throw a LogicException on
   * every real-network success. With the wire-up in place, a payload
   * shaped like `{ok: true, result: {message_id: 1, date: 0, chat: …}}`
   * lands as a typed `Message` instance on `Response::result`.
   */
  public function testBuildResponseLoadsTypedObjectForOkPayload(): void
  {
    $bot = new MockedBot();
    $session = $bot->getMockedSession();
    $method = new SendMessage(chatId: 1, text: 'x');

    $payload = (string)json_encode([
      'ok' => true,
      'result' => [
        'message_id' => 99,
        'date' => 1_700_000_000,
        'chat' => ['id' => 42, 'type' => 'private'],
      ],
    ]);

    $response = $session->checkResponse(
      bot: $bot,
      method: $method,
      statusCode: 200,
      content: $payload,
    );

    self::assertTrue($response->ok);
    self::assertInstanceOf(Message::class, $response->result);
    self::assertSame(99, $response->result->messageId);
    self::assertSame(42, $response->result->chat->id);
  }

  /**
   * `list:X` ReturnsType — each element of the array result is loaded
   * through `Gruven\PhpBotGram\Types\X`. `GetUpdates::ReturnsType` is
   * `list:Update`.
   */
  public function testBuildResponseLoadsListOfTypedObjects(): void
  {
    $bot = new MockedBot();
    $session = $bot->getMockedSession();
    $method = new GetUpdates();

    $payload = (string)json_encode([
      'ok' => true,
      'result' => [
        ['update_id' => 1],
        ['update_id' => 2],
      ],
    ]);

    $response = $session->checkResponse(
      bot: $bot,
      method: $method,
      statusCode: 200,
      content: $payload,
    );

    self::assertTrue($response->ok);
    self::assertIsArray($response->result);
    self::assertCount(2, $response->result);
    $first = $response->result[0];
    self::assertInstanceOf(Update::class, $first);
    self::assertSame(1, $first->updateId);
    $second = $response->result[1];
    self::assertInstanceOf(Update::class, $second);
    self::assertSame(2, $second->updateId);
  }

  /**
   * Scalar `bool` ReturnsType passes through verbatim — no Serializer
   * round-trip, the wire value lands on `Response::result` as-is.
   */
  public function testBuildResponseLoadsScalarBool(): void
  {
    $bot = new MockedBot();
    $session = $bot->getMockedSession();
    $method = new DeleteMessage(chatId: 1, messageId: 2);

    $payload = (string)json_encode(['ok' => true, 'result' => true]);

    $response = $session->checkResponse(
      bot: $bot,
      method: $method,
      statusCode: 200,
      content: $payload,
    );

    self::assertTrue($response->ok);
    self::assertTrue($response->result);
  }

  /**
   * Type-mismatch wire payload — `'bool'` ReturnsType against a dict-shaped
   * result is a remote-data error. Serializer wraps it in
   * `ClientDecodeException`. The buildResponse path keeps that contract.
   */
  public function testBuildResponseThrowsOnTypeMismatch(): void
  {
    $bot = new MockedBot();
    $session = $bot->getMockedSession();
    $method = new SendMessage(chatId: 1, text: 'x');

    $payload = (string)json_encode([
      'ok' => true,
      'result' => 'not-an-object', // SendMessage::ReturnsType is Message::class
    ]);

    $this->expectException(ClientDecodeException::class);
    $session->checkResponse(
      bot: $bot,
      method: $method,
      statusCode: 200,
      content: $payload,
    );
  }

  /**
   * Cycle 4 C1 fix: a `'union:Message|bool'` `ReturnsType` (emitted by
   * codegen for the seven `Edit*` / `setGameScore` methods that swap
   * between `Message` and `True` based on whether the edit targets an
   * inline message) dispatches by the raw response value's PHP type at
   * decode time. `result: true` → bool through; `result: {message_id, …}`
   * → loaded as `Message`.
   *
   * Pre-fix the path threw `ClientDecodeException` on every successful
   * inline-message edit because `Serializer::load(Message::class, true)`
   * sees a non-array and fails the type-mismatch guard.
   */
  public function testBuildResponseLoadsMessageOrBoolUnionAsBool(): void
  {
    $bot = new MockedBot();
    $session = $bot->getMockedSession();
    $method = new EditMessageText(text: 'x', inlineMessageId: 'inline-1');

    $payload = (string)json_encode(['ok' => true, 'result' => true]);

    $response = $session->checkResponse(
      bot: $bot,
      method: $method,
      statusCode: 200,
      content: $payload,
    );

    self::assertTrue($response->ok);
    self::assertTrue($response->result);
  }

  public function testBuildResponseLoadsMessageOrBoolUnionAsMessage(): void
  {
    $bot = new MockedBot();
    $session = $bot->getMockedSession();
    $method = new EditMessageText(text: 'x', chatId: 1, messageId: 99);

    $payload = (string)json_encode([
      'ok' => true,
      'result' => [
        'message_id' => 99,
        'date' => 1_700_000_000,
        'chat' => ['id' => 42, 'type' => 'private'],
      ],
    ]);

    $response = $session->checkResponse(
      bot: $bot,
      method: $method,
      statusCode: 200,
      content: $payload,
    );

    self::assertTrue($response->ok);
    self::assertInstanceOf(Message::class, $response->result);
    self::assertSame(99, $response->result->messageId);
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
