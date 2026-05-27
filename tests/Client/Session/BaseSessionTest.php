<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client\Session;

use Closure;
use DateInterval;
use DateTimeImmutable;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotDefault;
use Gruven\PhpBotGram\Client\DefaultBotProperties;
use Gruven\PhpBotGram\Client\Session\AmphpSession;
use Gruven\PhpBotGram\Client\Session\Middleware\BaseRequestMiddleware;
use Gruven\PhpBotGram\Client\TelegramApiServer;
use Gruven\PhpBotGram\Enums\ChatType;
use Gruven\PhpBotGram\Enums\ParseMode;
use Gruven\PhpBotGram\Enums\TopicIconColor;
use Gruven\PhpBotGram\Exceptions\ClientDecodeException;
use Gruven\PhpBotGram\Exceptions\RestartingTelegram;
use Gruven\PhpBotGram\Exceptions\TelegramApiException;
use Gruven\PhpBotGram\Exceptions\TelegramBadRequestException;
use Gruven\PhpBotGram\Exceptions\TelegramConflictException;
use Gruven\PhpBotGram\Exceptions\TelegramEntityTooLarge;
use Gruven\PhpBotGram\Exceptions\TelegramForbiddenException;
use Gruven\PhpBotGram\Exceptions\TelegramMigrateToChat;
use Gruven\PhpBotGram\Exceptions\TelegramNotFoundException;
use Gruven\PhpBotGram\Exceptions\TelegramRetryAfter;
use Gruven\PhpBotGram\Exceptions\TelegramServerException;
use Gruven\PhpBotGram\Exceptions\TelegramUnauthorizedException;
use Gruven\PhpBotGram\Methods\DeleteMessage;
use Gruven\PhpBotGram\Methods\EditMessageText;
use Gruven\PhpBotGram\Methods\GetUpdates;
use Gruven\PhpBotGram\Methods\SendMessage;
use Gruven\PhpBotGram\Methods\TelegramMethod;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Tests\Support\MockedSession;
use Gruven\PhpBotGram\Types\LinkPreviewOptions;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\Unspecified;
use Gruven\PhpBotGram\Types\Update;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Upstream: tests/test_api/test_client/test_session/test_base_session.py
 *
 * Upstream skips:
 *   - test_make_request (CustomSession.make_request is async) — API divergence (a):
 *     PHP BaseSession::makeRequest is synchronous; async behaviour is in AmphpSession
 *     which requires a live event loop. Structural coverage is provided by
 *     testInvokeDispatchesToSession in BotTest.
 *   - test_stream_content (AsyncGenerator) — API divergence (a): PHP's streamContent
 *     returns an Amp ReadableStream; requires a live Amp event loop. Covered
 *     structurally by BotDownloadTest.
 *   - test_context_manager (async with session) — API divergence (a): PHP sessions
 *     are not async context managers; lifetime management is done via explicit close().
 *   - test_add_middleware / test_use_middleware (mock async make_request) — test
 *     infrastructure divergence (c): covered by RequestMiddlewareManagerTest and
 *     BotTest::testInvokeRunsThroughMiddlewareChain.
 *
 * @internal
 */
final class BaseSessionTest extends TestCase
{
  // ── test_init_api / test_default_props / test_init_custom_api ───────────────

  /** Upstream: test_init_api — fresh session points at PRODUCTION. */
  public function testInitApiDefaultsToProduction(): void
  {
    $session = new MockedSession();
    $production = TelegramApiServer::production();
    self::assertSame($production->base, $session->api->base);
    self::assertSame($production->file, $session->api->file);
  }

  /** Upstream: test_default_props — json_loads/json_dumps are callable. */
  public function testDefaultPropsJsonCallablesAreSet(): void
  {
    $session = new MockedSession();
    // json_loads should decode a JSON string.
    $decoded = ($session->jsonLoads)('{"a":1}');
    self::assertSame(['a' => 1], $decoded);
    // json_dumps should encode an array.
    $encoded = ($session->jsonDumps)(['b' => 2]);
    self::assertSame('{"b":2}', $encoded);
  }

  /** Upstream: test_init_custom_api — AmphpSession accepts a custom api at construction. */
  public function testInitCustomApi(): void
  {
    // MockedSession does not forward `api` to parent; use AmphpSession directly.
    $customApi = TelegramApiServer::fromBase('http://example.com');
    $session = new AmphpSession(api: $customApi);
    self::assertSame($customApi, $session->api);
    self::assertStringContainsString('example.com', $session->api->base);
  }

  // ── test_prepare_value parametrize rows ─────────────────────────────────────

  /**
   * Upstream: test_prepare_value — parametrized table of scalar/enum/datetime inputs.
   */
  #[DataProvider('prepareValueProvider')]
  public function testPrepareValueTable(mixed $input, mixed $expected): void
  {
    $bot = new MockedBot();
    $session = $bot->getMockedSession();
    $files = [];
    $result = $session->prepareValue($input, $bot, $files);
    self::assertSame($expected, $result);
  }

  /**
   * @return array<string, array{mixed, mixed}>
   */
  public static function prepareValueProvider(): array
  {
    return [
      'null' => [null, null],
      'string text' => ['text', 'text'],
      'ChatType::Private enum' => [ChatType::Private, 'private'],
      'TopicIconColor::Red int enum' => [TopicIconColor::Red, '16478047'],
      'int 42' => [42, '42'],
      'bool true' => [true, 'true'],
      'list of strings' => [['test'], '["test"]'],
      'nested list' => [['test', ['test']], '["test",["test"]]'],
      'list with null-filtered dict' => [[['test' => 'pass', 'spam' => null]], '[{"test":"pass"}]'],
      'dict with null-filtered keys' => [['test' => 'pass', 'number' => 42, 'spam' => null], '{"test":"pass","number":42}'],
      'nested dict with null' => [['foo' => ['test' => 'pass', 'spam' => null]], '{"foo":{"test":"pass"}}'],
    ];
  }

  /**
   * Upstream: test_prepare_value with datetime — UNIX timestamp string.
   * Covers the `datetime.datetime(year=2017, ...)` row from upstream table.
   */
  public function testPrepareValueDatetime(): void
  {
    $bot = new MockedBot();
    $session = $bot->getMockedSession();
    $files = [];
    $dt = new DateTimeImmutable('@1494994302');
    $result = $session->prepareValue($dt, $bot, $files);
    self::assertSame('1494994302', $result);
  }

  /**
   * Upstream: test_prepare_value with LinkPreviewOptions dict row.
   * Covers `{"link_preview": LinkPreviewOptions(is_disabled=True)}` upstream row.
   */
  public function testPrepareValueLinkPreviewOptionsDict(): void
  {
    $bot = new MockedBot();
    $session = $bot->getMockedSession();
    $files = [];
    $value = ['link_preview' => new LinkPreviewOptions(isDisabled: true)];
    $result = $session->prepareValue($value, $bot, $files);
    self::assertSame('{"link_preview":{"is_disabled":true}}', $result);
  }

  /** Upstream: test_prepare_value_timedelta — DateInterval produces a string. */
  public function testPrepareValueTimedelta(): void
  {
    $bot = new MockedBot();
    $session = $bot->getMockedSession();
    $files = [];
    $interval = new DateInterval('PT2M'); // 2 minutes
    $result = $session->prepareValue($interval, $bot, $files);
    self::assertIsString($result);
    self::assertGreaterThan(0, (int)$result);
  }

  /** Upstream: test_prepare_value_defaults_replace — Default resolves to bot property. */
  public function testPrepareValueDefaultsReplace(): void
  {
    $bot = new Bot(
      token: '1:test',
      session: new MockedSession(),
      defaultProperties: new DefaultBotProperties(
        parseMode: ParseMode::Html->value,
        protectContent: true,
        linkPreviewIsDisabled: true,
      ),
    );
    $session = $bot->session;
    self::assertInstanceOf(MockedSession::class, $session);
    $files = [];

    self::assertSame(ParseMode::Html->value, $session->prepareValue(new BotDefault('parse_mode'), $bot, $files, dumpsJson: false));
    self::assertSame('true', $session->prepareValue(new BotDefault('link_preview_is_disabled'), $bot, $files));
    self::assertSame('true', $session->prepareValue(new BotDefault('protect_content'), $bot, $files));
  }

  /** Upstream: test_prepare_value_defaults_unset — UNSET sentinels resolve to null. */
  public function testPrepareValueDefaultsUnset(): void
  {
    $bot = new Bot(token: '1:test', session: new MockedSession());
    $session = $bot->session;
    self::assertInstanceOf(MockedSession::class, $session);
    $files = [];

    // An unset default property (no parse_mode configured) produces null.
    self::assertNull($session->prepareValue(new BotDefault('parse_mode'), $bot, $files, dumpsJson: false));
    self::assertNull($session->prepareValue(new BotDefault('protect_content'), $bot, $files, dumpsJson: false));
    self::assertNull($session->prepareValue(new BotDefault('link_preview_is_disabled'), $bot, $files, dumpsJson: false));
  }

  // ── test_check_response parametrize rows ────────────────────────────────────

  /**
   * Upstream: test_check_response — full parametrized table of status codes.
   *
   * @param null|class-string<Throwable> $expectedExceptionClass
   */
  #[DataProvider('checkResponseProvider')]
  public function testCheckResponseTable(int $statusCode, string $content, ?string $expectedExceptionClass): void
  {
    $bot = new MockedBot();
    $session = $bot->getMockedSession();
    $method = new DeleteMessage(chatId: 42, messageId: 42);

    if ($expectedExceptionClass === null) {
      $response = $session->checkResponse(bot: $bot, method: $method, statusCode: $statusCode, content: $content);
      self::assertTrue($response->ok);

      return;
    }

    $this->expectException($expectedExceptionClass);
    $session->checkResponse(bot: $bot, method: $method, statusCode: $statusCode, content: $content);
  }

  /**
   * @return array<string, array{int, string, null|class-string<Throwable>}>
   */
  public static function checkResponseProvider(): array
  {
    return [
      '200 ok' => [200, '{"ok":true,"result":true}', null],
      '400 bad request' => [400, '{"ok":false,"description":"test"}', TelegramBadRequestException::class],
      '400 retry_after' => [400, '{"ok":false,"description":"test","parameters":{"retry_after":1}}', TelegramRetryAfter::class],
      '400 migrate_to_chat' => [400, '{"ok":false,"description":"test","parameters":{"migrate_to_chat_id":-42}}', TelegramMigrateToChat::class],
      '404 not found' => [404, '{"ok":false,"description":"test"}', TelegramNotFoundException::class],
      '401 unauthorized' => [401, '{"ok":false,"description":"test"}', TelegramUnauthorizedException::class],
      '403 forbidden' => [403, '{"ok":false,"description":"test"}', TelegramForbiddenException::class],
      '409 conflict' => [409, '{"ok":false,"description":"test"}', TelegramConflictException::class],
      '413 entity too large' => [413, '{"ok":false,"description":"test"}', TelegramEntityTooLarge::class],
      '500 restarting' => [500, '{"ok":false,"description":"restarting"}', RestartingTelegram::class],
      '500 server error' => [500, '{"ok":false,"description":"test"}', TelegramServerException::class],
      '502 bad gateway' => [502, '{"ok":false,"description":"test"}', TelegramServerException::class],
      '499 generic api error' => [499, '{"ok":false,"description":"test"}', TelegramApiException::class],
    ];
  }

  /** Upstream: test_check_response_json_decode_error — invalid JSON throws ClientDecodeException. */
  public function testCheckResponseJsonDecodeError(): void
  {
    $bot = new MockedBot();
    $session = $bot->getMockedSession();
    $method = new DeleteMessage(chatId: 42, messageId: 42);

    $this->expectException(ClientDecodeException::class);
    $session->checkResponse(bot: $bot, method: $method, statusCode: 200, content: 'is not a JSON object');
  }

  /**
   * Upstream: test_check_response_validation_error — ok not a bool → ClientDecodeException.
   *
   * PHP divergence note: upstream passes `{"ok":"test"}` and Pydantic raises
   * ValidationError because the schema expects ok=bool. PHP's json_decode returns
   * ok="test" as a string; our guard is `=== true`, so a string-ok falls to the
   * error branch (not a decode error). The PHP-idiomatic equivalent is a 200
   * response where result shape contradicts the method's ReturnsType, which does
   * produce ClientDecodeException through Serializer::load.
   */
  public function testCheckResponseValidationErrorThrowsOnTypeMismatch(): void
  {
    $bot = new MockedBot();
    $session = $bot->getMockedSession();
    // SendMessage expects a Message object; feeding it a scalar triggers ClientDecodeException.
    $method = new SendMessage(chatId: 42, text: 'test');

    $this->expectException(ClientDecodeException::class);
    $session->checkResponse(
      bot: $bot,
      method: $method,
      statusCode: 200,
      content: '{"ok":true,"result":"not-an-object"}',
    );
  }

  // ── test_add_middleware ──────────────────────────────────────────────────────

  /** Upstream: test_add_middleware — register / membership check. Covered also in RequestMiddlewareManagerTest. */
  public function testAddMiddleware(): void
  {
    $session = new MockedSession();
    self::assertCount(0, $session->middleware);

    $mw = new class extends BaseRequestMiddleware {
      public function __invoke(Closure $next, Bot $bot, TelegramMethod $method, ?int $timeout = null): mixed
      {
        return $next($bot, $method, $timeout);
      }
    };

    $session->middleware->register($mw);
    self::assertCount(1, $session->middleware);
    self::assertTrue(isset($session->middleware[0]));
    $session->middleware->unregister($mw);
    self::assertCount(0, $session->middleware);
  }

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
