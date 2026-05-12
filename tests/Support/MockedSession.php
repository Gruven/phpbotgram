<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Support;

use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableStream;
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
  public bool $closed = true;

  public function __construct()
  {
    parent::__construct();
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

    return $this->requests->pop();
  }

  public function makeRequest(Bot $bot, TelegramMethod $method, ?int $timeout = null): mixed
  {
    $this->closed = false;
    $this->requests->push($method);

    if ($this->responses->isEmpty()) {
      throw new RuntimeException('No canned responses left');
    }

    return $this->responses->pop()->result;
  }

  public function close(): void
  {
    $this->closed = true;
  }

  public function streamContent(string $url, array $headers = [], int $timeout = 30, int $chunkSize = 65536, bool $raiseForStatus = true): ReadableStream
  {
    return new ReadableBuffer('');
  }
}
