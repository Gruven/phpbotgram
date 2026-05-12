<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client\Session;

use Amp\ByteStream\ReadableStream;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Client\TelegramApiServer;
use Gruven\PhpBotGram\Exceptions\TelegramNetworkException;
use Gruven\PhpBotGram\Methods\TelegramMethod;
use Gruven\PhpBotGram\Types\InputFile;
use RuntimeException;
use Throwable;

/**
 * Production session backed by amphp/http-client (Fiber-aware HTTP/1.1).
 *
 * Phase 1 implements form-urlencoded body encoding only. Multipart/form-data
 * with InputFile streaming lands in Phase 6 when the webhook layer needs
 * symmetric multipart handling.
 */
final class AmphpSession extends BaseSession
{
  private ?HttpClient $client = null;

  public function __construct(
    public readonly ?string $proxy = null,
    public readonly int $limit = 100,
    ?TelegramApiServer $api = null,
    float $timeout = 60.0,
  ) {
    parent::__construct(api: $api, timeout: $timeout);
  }

  private function client(): HttpClient
  {
    return $this->client ??= (new HttpClientBuilder())->build();
  }

  /**
   * @param TelegramMethod<mixed> $method
   */
  public function makeRequest(Bot $bot, TelegramMethod $method, ?int $timeout = null): mixed
  {
    $url = $this->api->apiUrl($bot->token, $method::ApiMethod);

    /** @var array<string, InputFile> $files */
    $files = [];
    $body = $this->buildFormBody($bot, $method, $files);

    if ($files !== []) {
      throw new RuntimeException('AmphpSession Phase 1 does not yet support InputFile uploads — multipart support lands in Phase 6');
    }

    $request = new Request($url, 'POST');
    $request->setHeader('Content-Type', 'application/x-www-form-urlencoded');
    $request->setBody($body);
    $effectiveTimeout = (float)($timeout ?? $this->timeout);
    $request->setTcpConnectTimeout($effectiveTimeout);
    $request->setTlsHandshakeTimeout($effectiveTimeout);
    $request->setTransferTimeout($effectiveTimeout);

    try {
      $response = $this->client()->request($request);
      $content = $response->getBody()->buffer();
    } catch (Throwable $e) {
      throw new TelegramNetworkException($method, $e::class . ': ' . $e->getMessage());
    }

    $resp = $this->checkResponse($bot, $method, $response->getStatus(), $content);

    return $resp->result;
  }

  public function close(): void
  {
    $this->client = null;
  }

  /**
   * @param array<string, string> $headers
   */
  public function streamContent(string $url, array $headers = [], int $timeout = 30, int $chunkSize = 65536, bool $raiseForStatus = true): ReadableStream
  {
    $request = new Request($url, 'GET');

    foreach ($headers as $k => $v) {
      if ($k === '') {
        continue;
      }
      $request->setHeader($k, $v);
    }
    $request->setTransferTimeout((float)$timeout);
    $response = $this->client()->request($request);

    if ($raiseForStatus && $response->getStatus() >= 400) {
      throw new RuntimeException("HTTP {$response->getStatus()} fetching {$url}");
    }

    return $response->getBody();
  }

  /**
   * @param TelegramMethod<mixed> $method
   * @param array<string, InputFile> $files
   */
  private function buildFormBody(Bot $bot, TelegramMethod $method, array &$files): string
  {
    $dumped = Serializer::dump($method);
    $fields = [];

    foreach ($dumped as $key => $value) {
      $prepared = $this->prepareValue($value, $bot, $files);

      if ($prepared === null) {
        continue;
      }
      $fields[$key] = $prepared;
    }

    return http_build_query($fields);
  }
}
