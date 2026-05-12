<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client\Session;

use Amp\ByteStream\ReadableStream;
use BackedEnum;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Gruven\PhpBotGram\Bot;
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
use JsonException;
use RuntimeException;

abstract class BaseSession
{
  public readonly TelegramApiServer $api;
  public private(set) RequestMiddlewareManager $middleware;

  public function __construct(
    ?TelegramApiServer $api = null,
    public readonly float $timeout = 60.0,
  ) {
    $this->api = $api ?? TelegramApiServer::production();
    $this->middleware = new RequestMiddlewareManager();
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
    $chain = $this->middleware->wrap($this->makeRequest(...));

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
      $data = json_decode($content, associative: true, flags: JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
      throw new ClientDecodeException('Failed to decode response', $e, $content);
    }

    if (!is_array($data)) {
      throw new ClientDecodeException('Response is not an object', new RuntimeException('Non-object response'), $content);
    }

    $response = $this->buildResponse($bot, $method, $data);

    if ($statusCode >= 200 && $statusCode < 300 && $response->ok) {
      return $response;
    }

    $description = isset($data['description']) && is_string($data['description']) ? $data['description'] : '';
    $params = $data['parameters'] ?? null;

    if (is_array($params)) {
      if (isset($params['retry_after']) && is_int($params['retry_after'])) {
        throw new TelegramRetryAfter($method, $description, retryAfter: $params['retry_after']);
      }

      if (isset($params['migrate_to_chat_id']) && is_int($params['migrate_to_chat_id'])) {
        throw new TelegramMigrateToChat($method, $description, migrateToChatId: $params['migrate_to_chat_id']);
      }
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
    if ($value === null) {
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
        return json_encode($prepared, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
      }

      return $prepared;
    }

    if ($value instanceof DateInterval) {
      $now = new DateTimeImmutable();

      return (string)$now->add($value)->getTimestamp();
    }

    if ($value instanceof DateTimeInterface) {
      return (string)$value->getTimestamp();
    }

    if ($value instanceof BackedEnum) {
      return $this->prepareValue($value->value, $bot, $files);
    }

    if ($value instanceof TelegramObject) {
      $dumped = Serializer::dump($value);

      return $this->prepareValue($dumped, $bot, $files, dumpsJson: $dumpsJson);
    }

    if ($value === Unspecified::instance()) {
      return null;
    }

    if ($dumpsJson) {
      return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    return $value;
  }

  /**
   * Phase 1 shim — builds a Response with `result: null`. The exception-path
   * branches of checkResponse don't need a real result. Phase 2 codegen wires
   * this through Serializer::load against $method::ReturnsType so the result
   * becomes a typed TelegramObject.
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

    return new Response(
      ok: (bool)($data['ok'] ?? false),
      result: null,
      description: $description,
      errorCode: $errorCode,
    );
  }
}
