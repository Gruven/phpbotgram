<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Webhook;

use function Amp\async;

use Amp\Future;

use function Amp\Future\await;

use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Methods\TelegramMethod;

/**
 * Abstract base for webhook request handlers.
 *
 * Port of `aiogram.webhook.aiohttp_server.BaseRequestHandler` (lines 84–208).
 *
 * ## Simplified "webhook reply" approach
 *
 * The upstream supports returning a `TelegramMethod` directly as the HTTP
 * response body (the "webhook reply" trick), which saves a round-trip to
 * the Telegram API.  Faithfully porting the multipart/form-data serialiser
 * that assembles that response is out of scope for this task.
 *
 * **Current behaviour (v0 simplification):** `handleRequest` always returns
 * `200 OK` with `{}` as the body.  When the dispatcher's dispatch chain
 * returns a `TelegramMethod`, it is routed through
 * `Dispatcher::silentCallRequest` so the API call still happens — just as
 * a second outbound request rather than an inline webhook reply.
 *
 * The multipart writer and the webhook-reply optimisation are deferred to a
 * later task (Task 6.5 / Phase 6 follow-up).
 *
 * ## Background-mode
 *
 * When `$handleInBackground` is `true`, the dispatcher call is detached into
 * an Amp fiber and the 200 response is sent immediately.  In-flight fibers
 * are tracked in `$backgroundTasks` so callers can wait for them on
 * shutdown.
 *
 * @internal
 */
abstract class BaseRequestHandler implements RequestHandler
{
  /**
   * Extra workflow kwargs forwarded to
   * `Dispatcher::feedWebhookUpdate` / `feedRawUpdate` on every request.
   *
   * @var array<string, mixed>
   */
  protected readonly array $data;

  /**
   * In-flight background-dispatch fibers, keyed by `spl_object_id`.
   *
   * Populated in {@see handleRequestBackground}; each entry self-cleans
   * via `Future::finally` once the fiber settles.
   *
   * @var array<int, Future<void>>
   */
  private array $backgroundTasks = [];

  /**
   * @param array<string, mixed> $data Extra kwargs forwarded to feedWebhookUpdate.
   */
  public function __construct(
    protected readonly Dispatcher $dispatcher,
    protected readonly bool $handleInBackground = false,
    array $data = [],
  ) {
    $this->data = $data;
  }

  // -------------------------------------------------------------------------
  // Abstract contract
  // -------------------------------------------------------------------------

  /**
   * Release any resources held by this handler (e.g. bot session pools).
   *
   * Mirrors upstream `BaseRequestHandler.close()`.  The webhook server
   * adapter should call this method when the HTTP server shuts down.
   */
  abstract public function close(): void;

  /**
   * Resolve the `Bot` instance to use for this incoming request.
   *
   * For single-bot setups this typically just returns the pre-configured
   * bot; multi-token setups may inspect the URL path or a header.
   *
   * @param Request $request The incoming HTTP request.
   */
  abstract public function resolveBot(Request $request): Bot;

  /**
   * Validate the `X-Telegram-Bot-Api-Secret-Token` header value against
   * the secret registered for `$bot`.
   *
   * Return `true` to accept the request; `false` to reject with 401.
   * The default implementation on the abstract base has no body — every
   * concrete subclass **must** override this method.
   *
   * @param string $telegramSecretToken The raw header value (may be empty
   *                                    when the header is absent).
   * @param Bot $bot The bot resolved for this request.
   */
  abstract public function verifySecret(string $telegramSecretToken, Bot $bot): bool;

  // -------------------------------------------------------------------------
  // RequestHandler implementation
  // -------------------------------------------------------------------------

  /**
   * Entry-point called by amphp/http-server for every incoming POST.
   *
   * Flow:
   * 1. Resolve the bot for this request.
   * 2. Check the secret token header.
   * 3. Dispatch in-line or in the background depending on `$handleInBackground`.
   */
  final public function handleRequest(Request $request): Response
  {
    $bot = $this->resolveBot($request);
    $token = $request->getHeader('X-Telegram-Bot-Api-Secret-Token') ?? '';

    if (!$this->verifySecret($token, $bot)) {
      return new Response(401, [], 'Unauthorized');
    }

    if ($this->handleInBackground) {
      return $this->handleRequestBackground($bot, $request);
    }

    return $this->handleRequestInline($bot, $request);
  }

  // -------------------------------------------------------------------------
  // Shutdown helper
  // -------------------------------------------------------------------------

  /**
   * Await all in-flight background tasks spawned by handleRequestBackground().
   *
   * Call this during graceful shutdown (Setup::register and AmphpServer::run
   * wire it into the onStop callback) to ensure FSM writes and outbound API
   * calls complete before the server shuts down.
   */
  public function awaitBackgroundTasks(): void
  {
    if ($this->backgroundTasks === []) {
      return;
    }

    await($this->backgroundTasks);
  }

  // -------------------------------------------------------------------------
  // Registration helper
  // -------------------------------------------------------------------------

  /**
   * Register this handler at `$path` using the provided registration
   * callback.
   *
   * Since `amphp/http-server-router` is an optional dependency not
   * bundled in this project, the caller supplies a routing callback
   * rather than a concrete router type.  Typical usage with
   * `amphp/http-server-router`:
   *
   * ```php
   * $handler->register(
   *     fn (string $path, RequestHandler $h) => $router->addRoute('POST', $path, $h),
   *     '/webhook',
   * );
   * ```
   *
   * @param callable(string, RequestHandler): void $registerRoute
   *                                                              A callback that registers a POST route for the given path.
   * @param string $path The URL path to bind (e.g. `'/webhook'`).
   */
  public function register(callable $registerRoute, string $path): void
  {
    $registerRoute($path, $this);
  }

  // -------------------------------------------------------------------------
  // Private dispatch helpers
  // -------------------------------------------------------------------------

  /**
   * Dispatch synchronously and return the response only after the
   * dispatcher chain has completed.
   *
   * Simplified from upstream: the result `TelegramMethod` (if any) is
   * routed via `silentCallRequest` rather than embedded in the HTTP
   * response body.  See class-level docblock for the deferral rationale.
   */
  private function handleRequestInline(Bot $bot, Request $request): Response
  {
    $body = $request->getBody()->buffer();

    /** @var array<string, mixed> $payload */
    $payload = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);

    $result = $this->dispatcher->feedWebhookUpdate($bot, $payload, $this->data);

    if ($result instanceof TelegramMethod) {
      $this->dispatcher->silentCallRequest($bot, $result);
    }

    return new Response(200, ['Content-Type' => 'application/json'], '{}');
  }

  /**
   * Detach the dispatch into a background fiber and return 200 immediately.
   *
   * The response is sent before the Telegram update has been processed.
   * Any `TelegramMethod` produced by a handler is routed via
   * `silentCallRequest` inside the background fiber.
   *
   * In-flight fibers are tracked in `$backgroundTasks` so callers can
   * drain them on shutdown (e.g. via `Future\await($this->backgroundTasks)`
   * in a shutdown observer).
   */
  private function handleRequestBackground(Bot $bot, Request $request): Response
  {
    $body = $request->getBody()->buffer();

    /** @var array<string, mixed> $payload */
    $payload = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);

    /** @var Future<void> $task */
    $task = async(function () use ($bot, $payload): void {
      $result = $this->dispatcher->feedRawUpdate($bot, $payload, $this->data);

      if ($result instanceof TelegramMethod) {
        $this->dispatcher->silentCallRequest($bot, $result);
      }
    });

    $taskId = spl_object_id($task);
    $this->backgroundTasks[$taskId] = $task;
    $task->finally(function () use ($taskId): void {
      unset($this->backgroundTasks[$taskId]);
    });

    return new Response(200, ['Content-Type' => 'application/json'], '{}');
  }
}
