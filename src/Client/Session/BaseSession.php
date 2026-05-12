<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client\Session;

use Amp\ByteStream\ReadableStream;
use BackedEnum;
use Closure;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotContextController;
use Gruven\PhpBotGram\Client\BotDefault;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Client\Session\Middleware\RequestMiddlewareManager;
use Gruven\PhpBotGram\Client\TelegramApiServer;
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
use Gruven\PhpBotGram\Methods\Response;
use Gruven\PhpBotGram\Methods\TelegramMethod;
use Gruven\PhpBotGram\Types\InputFile;
use Gruven\PhpBotGram\Types\TelegramObject;
use Gruven\PhpBotGram\Types\Unspecified;
use LogicException;
use RuntimeException;
use Throwable;

abstract class BaseSession
{
  public readonly TelegramApiServer $api;

  /** @var Closure(string): mixed */
  public readonly Closure $jsonLoads;

  /** @var Closure(mixed): string */
  public readonly Closure $jsonDumps;
  public private(set) RequestMiddlewareManager $middleware;

  /**
   * Stable first-class-callable reference to makeRequest, captured once at
   * construction. Re-creating `$this->makeRequest(...)` per invocation would
   * produce a fresh Closure each time and bust RequestMiddlewareManager's
   * chain cache (which keys by spl_object_id).
   */
  private readonly Closure $makeRequestRef;

  /**
   * @param null|Closure(string): mixed $jsonLoads injectable JSON decoder (parity with aiogram's BaseSession.json_loads). Defaults to `json_decode(..., true, JSON_THROW_ON_ERROR)`.
   * @param null|Closure(mixed): string $jsonDumps injectable JSON encoder (parity with aiogram's BaseSession.json_dumps). Defaults to `json_encode(..., JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)`.
   */
  public function __construct(
    ?TelegramApiServer $api = null,
    ?Closure $jsonLoads = null,
    ?Closure $jsonDumps = null,
    public readonly float $timeout = 60.0,
  ) {
    $this->api = $api ?? TelegramApiServer::production();
    $this->jsonLoads = $jsonLoads ?? static fn(string $payload): mixed => json_decode($payload, associative: true, flags: JSON_THROW_ON_ERROR);
    $this->jsonDumps = $jsonDumps ?? static fn(mixed $value): string => (string)json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    $this->middleware = new RequestMiddlewareManager();
    $this->makeRequestRef = $this->makeRequest(...);
  }

  /**
   * @param TelegramMethod<mixed> $method
   */
  abstract public function makeRequest(Bot $bot, TelegramMethod $method, ?int $timeout = null): mixed;

  /**
   * Run $method through the session's middleware chain, terminating at makeRequest.
   * Mirrors aiogram's `BaseSession.__call__` (session/base.py:267-274). With an empty
   * chain this is equivalent to calling makeRequest directly.
   *
   * @param TelegramMethod<mixed> $method
   */
  public function __invoke(Bot $bot, TelegramMethod $method, ?int $timeout = null): mixed
  {
    $chain = $this->middleware->wrap($this->makeRequestRef);

    return $chain($bot, $method, $timeout);
  }

  abstract public function close(): void;

  /**
   * @param array<string, string> $headers
   */
  abstract public function streamContent(string $url, array $headers = [], int $timeout = 30, int $chunkSize = 65536, bool $raiseForStatus = true): ReadableStream;

  /**
   * Maps Telegram error status codes to typed exceptions.
   *
   * @param TelegramMethod<mixed> $method
   *
   * @return Response<mixed>
   */
  public function checkResponse(Bot $bot, TelegramMethod $method, int $statusCode, string $content): Response
  {
    try {
      $data = ($this->jsonLoads)($content);
    } catch (Throwable $e) {
      // Catch \Throwable not just JsonException — user-supplied jsonLoads
      // closures may raise other exception types (UnexpectedValueException,
      // RuntimeException etc.) that should still be wrapped as decode errors.
      throw new ClientDecodeException('Failed to decode response', $e, $content);
    }

    if (!is_array($data)) {
      throw new ClientDecodeException('Response is not an object', new RuntimeException('Non-object response'), $content);
    }

    $response = $this->buildResponse($bot, $method, $data);

    if ($statusCode >= 200 && $statusCode < 300 && $response->ok) {
      return $response;
    }

    $description = $response->description ?? (isset($data['description']) && is_string($data['description']) ? $data['description'] : '');
    [$retryAfter, $migrateToChatId] = self::extractResponseParams($response, $data);

    if ($retryAfter !== null) {
      throw new TelegramRetryAfter($method, $description, retryAfter: $retryAfter);
    }

    if ($migrateToChatId !== null) {
      throw new TelegramMigrateToChat($method, $description, migrateToChatId: $migrateToChatId);
    }

    throw match (true) {
      $statusCode === 400 => new TelegramBadRequestException($method, $description),
      $statusCode === 401 => new TelegramUnauthorizedException($method, $description),
      $statusCode === 403 => new TelegramForbiddenException($method, $description),
      $statusCode === 404 => new TelegramNotFoundException($method, $description),
      $statusCode === 409 => new TelegramConflictException($method, $description),
      $statusCode === 413 => new TelegramEntityTooLarge($method, $description),
      $statusCode >= 500 && str_contains($description, 'restart') => new RestartingTelegram($method, $description),
      $statusCode >= 500 => new TelegramServerException($method, $description),
      default => new TelegramApiException($method, $description),
    };
  }

  /**
   * Resolves BotDefault sentinels, detaches InputFile to $files, encodes
   * datetimes / enums / nested TelegramObject. Port of upstream
   * session/base.py:179-250.
   *
   * @param array<string, InputFile> $files
   */
  public function prepareValue(mixed $value, Bot $bot, array &$files, bool $dumpsJson = true): mixed
  {
    if ($value === null || $value === Unspecified::instance()) {
      return null;
    }

    if (is_string($value)) {
      return $value;
    }

    if ($value instanceof BotDefault) {
      $resolved = $bot->getDefaultProperties()->get($value->name);

      return $this->prepareValue($resolved, $bot, $files, $dumpsJson);
    }

    if ($value instanceof InputFile) {
      $key = bin2hex(random_bytes(10));
      $files[$key] = $value;

      return "attach://{$key}";
    }

    if (is_array($value)) {
      $isList = array_is_list($value);
      $prepared = [];

      foreach ($value as $k => $item) {
        $p = $this->prepareValue($item, $bot, $files, dumpsJson: false);

        if ($p === null) {
          continue;
        }

        if ($isList) {
          $prepared[] = $p;
        } else {
          $prepared[$k] = $p;
        }
      }

      if ($dumpsJson) {
        return ($this->jsonDumps)($prepared);
      }

      return $prepared;
    }

    if ($value instanceof DateInterval) {
      $now = new DateTimeImmutable();

      return (string)(int)round((float)$now->add($value)->format('U.u'));
    }

    if ($value instanceof DateTimeInterface) {
      // Round fractional seconds (parity with aiogram's `str(round(value.timestamp()))`).
      // `getTimestamp()` truncates microseconds; a DateTime constructed from
      // sub-second-precision sources would otherwise differ from upstream by 1 second
      // on values whose microsecond component is >= 500_000.
      return (string)(int)round((float)$value->format('U.u'));
    }

    if ($value instanceof BackedEnum) {
      return $this->prepareValue($value->value, $bot, $files);
    }

    // Catch both TelegramObject (Types) and TelegramMethod (Methods) — they share
    // BotContextController as their nearest common ancestor. Without this a nested
    // TelegramMethod would fall through to json_encode, which invokes
    // BotDefault::jsonSerialize and throws LogicException.
    if ($value instanceof BotContextController) {
      $dumped = Serializer::dump($value);

      return $this->prepareValue($dumped, $bot, $files, dumpsJson: $dumpsJson);
    }

    if ($dumpsJson) {
      return ($this->jsonDumps)($value);
    }

    return $value;
  }

  /**
   * Reads `retry_after` / `migrate_to_chat_id` from the typed `$response->parameters`
   * when populated (Phase 2 codegen wires `buildResponse` to materialise it), falling
   * back to the raw `$data['parameters']` shape that the Phase 1 stub leaves untouched.
   * Single extraction point so Phase 2 has one site to retire.
   *
   * @param Response<mixed> $response
   * @param array<array-key, mixed> $data
   *
   * @return array{0: ?int, 1: ?int} [retryAfter, migrateToChatId]
   */
  private static function extractResponseParams(Response $response, array $data): array
  {
    $retryAfter = $response->parameters?->retryAfter;
    $migrateToChatId = $response->parameters?->migrateToChatId;

    if ($retryAfter !== null && $migrateToChatId !== null) {
      return [$retryAfter, $migrateToChatId];
    }
    $params = $data['parameters'] ?? null;

    if (!is_array($params)) {
      return [$retryAfter, $migrateToChatId];
    }

    if ($retryAfter === null && isset($params['retry_after']) && is_int($params['retry_after'])) {
      $retryAfter = $params['retry_after'];
    }

    if ($migrateToChatId === null && isset($params['migrate_to_chat_id']) && is_int($params['migrate_to_chat_id'])) {
      $migrateToChatId = $params['migrate_to_chat_id'];
    }

    return [$retryAfter, $migrateToChatId];
  }

  /**
   * Build a typed Response from the wire payload.
   *
   * On the happy path (`ok: true` + a present `result`), the raw result is
   * routed through `deserializeResult()` against the method's declared
   * `ReturnsType` const so the caller sees a typed TelegramObject (or list
   * thereof, or scalar). Error / non-ok payloads land here too — they
   * surface to `checkResponse()` which maps the status code to the
   * matching exception subclass without consulting `$response->result`.
   *
   * Wired in by Cycle 3: prior to this commit, BaseSession returned
   * `result: null` unconditionally — AmphpSession's success path would
   * then throw a LogicException because the typed Method return contract
   * couldn't be satisfied. The Method-class-side `ReturnsType` const is
   * emitted by codegen precisely so this hook can do the right thing
   * without per-method dispatch tables.
   *
   * @param TelegramMethod<mixed> $method
   * @param array<array-key, mixed> $data
   *
   * @return Response<mixed>
   */
  protected function buildResponse(Bot $bot, TelegramMethod $method, array $data): Response
  {
    $description = isset($data['description']) && is_string($data['description']) ? $data['description'] : null;
    $errorCode = isset($data['error_code']) && is_int($data['error_code']) ? $data['error_code'] : null;

    $result = null;

    if (($data['ok'] ?? false) === true && array_key_exists('result', $data)) {
      $result = $this->deserializeResult($method::class, $method::ReturnsType, $data['result'], $bot);
    }

    return new Response(
      ok: (bool)($data['ok'] ?? false),
      result: $result,
      description: $description,
      errorCode: $errorCode,
    );
  }

  /**
   * Recursively resolve `$returnsType` against `$raw`, returning a typed
   * value the caller can hand directly to a `: <Type>` return slot.
   *
   * Supports the three `Method::ReturnsType` shapes the codegen emits:
   *   - scalar names (`'bool'`, `'int'`, `'string'`, `'float'`) — passthrough;
   *   - `'list:<X>'` — recurse on each element with `<X>` as the inner type;
   *   - class-string — `Serializer::load($class, $raw, $bot)`.
   *
   * Inner names for the list form are short names in the schema's `Types\`
   * namespace (the codegen never emits FQCNs for the inner element). They
   * are prefixed before the class-string branch fires; if a future codegen
   * change emits something else, the class-string branch handles FQCNs too.
   *
   * @param class-string<TelegramMethod<mixed>> $methodClass for diagnostics
   */
  private function deserializeResult(string $methodClass, string $returnsType, mixed $raw, Bot $bot): mixed
  {
    if ($returnsType === '') {
      throw new LogicException(\sprintf(
        '%s::ReturnsType is empty — concrete method classes must set it (codegen emits the const).',
        $methodClass,
      ));
    }

    if ($returnsType === 'bool' || $returnsType === 'int' || $returnsType === 'string' || $returnsType === 'float') {
      return $raw;
    }

    if (str_starts_with($returnsType, 'list:')) {
      $inner = substr($returnsType, 5);

      if (!is_array($raw)) {
        throw new ClientDecodeException(
          \sprintf('Expected list for %s, got %s', $methodClass, get_debug_type($raw)),
          new RuntimeException('Type mismatch'),
          $raw,
        );
      }

      $items = [];

      foreach ($raw as $entry) {
        $items[] = $this->deserializeResult($methodClass, $inner, $entry, $bot);
      }

      return $items;
    }

    $class = $this->resolveResultClass($returnsType);

    if (!is_array($raw)) {
      throw new ClientDecodeException(
        \sprintf('Expected object payload for %s -> %s, got %s', $methodClass, $class, get_debug_type($raw)),
        new RuntimeException('Type mismatch'),
        $raw,
      );
    }

    /** @var array<string, mixed> $raw */
    return Serializer::load($class, $raw, $bot);
  }

  /**
   * Map a `Method::ReturnsType` token to a `class-string<TelegramObject>`.
   *
   * Codegen emits either a FQCN (`Foo::class` constant materialises as the
   * fully-qualified string) or a bare short name (the inner element of a
   * `list:X` sentinel). Both shapes flow into here.
   *
   * @return class-string<TelegramObject>
   */
  private function resolveResultClass(string $token): string
  {
    if (class_exists($token) && is_a($token, TelegramObject::class, allow_string: true)) {
      /** @var class-string<TelegramObject> $token */
      return $token;
    }

    $prefixed = 'Gruven\\PhpBotGram\\Types\\' . $token;

    if (class_exists($prefixed) && is_a($prefixed, TelegramObject::class, allow_string: true)) {
      /** @var class-string<TelegramObject> $prefixed */
      return $prefixed;
    }

    throw new LogicException(\sprintf('Unknown ReturnsType token %s', var_export($token, true)));
  }
}
