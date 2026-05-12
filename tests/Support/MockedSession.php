<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Support;

use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableStream;
use Closure;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Session\BaseSession;
use Gruven\PhpBotGram\Methods\Response;
use Gruven\PhpBotGram\Methods\TelegramMethod;
use RuntimeException;
use SplDoublyLinkedList;

final class MockedSession extends BaseSession
{
  /** @var SplDoublyLinkedList<Response<mixed>> */
  private SplDoublyLinkedList $responses;

  /** @var SplDoublyLinkedList<TelegramMethod<mixed>> */
  private SplDoublyLinkedList $requests;
  public bool $closed = false;

  /**
   * @param null|Closure(string): mixed $jsonLoads
   * @param null|Closure(mixed): string $jsonDumps
   */
  public function __construct(
    ?Closure $jsonLoads = null,
    ?Closure $jsonDumps = null,
  ) {
    parent::__construct(jsonLoads: $jsonLoads, jsonDumps: $jsonDumps);
    $this->responses = new SplDoublyLinkedList();
    $this->requests = new SplDoublyLinkedList();
  }

  /**
   * @param Response<mixed> $response
   *
   * @return Response<mixed>
   */
  public function addResult(Response $response): Response
  {
    $this->responses->push($response);

    return $response;
  }

  /**
   * @return TelegramMethod<mixed>
   */
  public function getRequest(): TelegramMethod
  {
    if ($this->requests->isEmpty()) {
      throw new RuntimeException('No recorded requests');
    }

    // FIFO drain — symmetric with `makeRequest`'s response queue. The
    // upstream aiogram MockedSession's `get_request()` likewise pops from
    // the head so callers can inspect requests in dispatch order.
    return $this->requests->shift();
  }

  public function makeRequest(Bot $bot, TelegramMethod $method, ?int $timeout = null): mixed
  {
    $this->closed = false;
    $this->requests->push($method);

    if ($this->responses->isEmpty()) {
      throw new RuntimeException('No canned responses left');
    }
    // FIFO: requests in the order they were queued. SplDoublyLinkedList::push
    // appends to the tail, so a `shift()` (remove head) gives the earliest
    // queued response. Necessary for any test that queues multiple responses
    // and expects them consumed in registration order — single-response tests
    // are unaffected. Matches upstream aiogram MockedBot's `responses.popleft()`.
    $response = $this->responses->shift();

    // Mirror aiogram's MockedBot.make_request: error responses (ok=false) get
    // routed through checkResponse so the typed exception mapping is exercised
    // even against canned data. Happy-path responses short-circuit because
    // BaseSession::buildResponse is a Phase 1 stub that hard-codes result=null
    // — going through checkResponse would lose the user-supplied result.
    if (!$response->ok) {
      $statusCode = $response->errorCode ?? 500;
      $payload = [
        'ok' => false,
        'description' => $response->description ?? '',
        'error_code' => $statusCode,
      ];

      if ($response->parameters !== null) {
        $params = [];

        if ($response->parameters->retryAfter !== null) {
          $params['retry_after'] = $response->parameters->retryAfter;
        }

        if ($response->parameters->migrateToChatId !== null) {
          $params['migrate_to_chat_id'] = $response->parameters->migrateToChatId;
        }

        if ($params !== []) {
          $payload['parameters'] = $params;
        }
      }
      $this->checkResponse($bot, $method, $statusCode, (string)json_encode($payload, JSON_THROW_ON_ERROR));

      throw new RuntimeException('checkResponse did not raise on non-ok response — should be unreachable');
    }

    return $response->result;
  }

  public function close(): void
  {
    $this->closed = true;
  }

  /** @var array<string, string> map<url, body> for streamContent canned responses */
  public array $cannedStreamBodies = [];

  /** @var list<string> recorded streamContent URLs */
  public array $streamedUrls = [];

  public function streamContent(string $url, array $headers = [], int $timeout = 30, int $chunkSize = 65536, bool $raiseForStatus = true): ReadableStream
  {
    $this->streamedUrls[] = $url;
    $body = $this->cannedStreamBodies[$url] ?? '';

    return new ReadableBuffer($body);
  }
}
