<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Webhook;

use Amp\Http\Server\Request;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;

/**
 * Single-bot webhook handler with optional constant-time secret-token validation.
 *
 * Port of `aiogram.webhook.aiohttp_server.SimpleRequestHandler` (lines 212–247).
 *
 * ## Secret-token validation
 *
 * When `$secretToken` is provided (non-null, non-empty), the
 * `X-Telegram-Bot-Api-Secret-Token` header value sent by Telegram is compared
 * against it using {@see hash_equals} — the PHP stdlib equivalent of Python's
 * `secrets.compare_digest`. Both functions use constant-time comparison to
 * prevent timing-attack leakage of the stored secret.
 *
 * **Empty-string edge case:** PHP's `""` is truthy unlike Python's `""` which
 * is falsy. An explicit `=== null || === ''` guard ensures that passing an
 * empty string as `$secretToken` has the same open-access semantics as
 * passing `null` — matching upstream's `if self.secret_token:` test.
 *
 * ## Background mode
 *
 * `$handleInBackground` defaults to `true` here (upstream default), whereas
 * {@see BaseRequestHandler} defaults to `false`. Callers that need
 * synchronous dispatch can pass `handleInBackground: false` explicitly.
 *
 * @internal
 */
final class SimpleRequestHandler extends BaseRequestHandler
{
  /**
   * @param array<string, mixed> $data Extra kwargs forwarded to feedWebhookUpdate.
   */
  public function __construct(
    Dispatcher $dispatcher,
    private readonly Bot $bot,
    bool $handleInBackground = true,
    private readonly ?string $secretToken = null,
    array $data = [],
  ) {
    parent::__construct($dispatcher, $handleInBackground, $data);
  }

  /**
   * Validate the Telegram secret-token header value.
   *
   * Returns `true` (accept) when no secret is configured, or when the
   * supplied header value matches the configured secret via a constant-time
   * comparison. Returns `false` (reject) otherwise.
   *
   * @param string $telegramSecretToken The raw header value (empty string when absent).
   * @param Bot $bot The resolved bot for this request (unused here).
   */
  public function verifySecret(string $telegramSecretToken, Bot $bot): bool
  {
    if ($this->secretToken === null || $this->secretToken === '') {
      return true;
    }

    // hash_equals is constant-time — mirrors Python's secrets.compare_digest.
    return hash_equals($this->secretToken, $telegramSecretToken);
  }

  /**
   * Close the bot's underlying HTTP session / connection pool.
   */
  public function close(): void
  {
    $this->bot->session->close();
  }

  /**
   * Return the pre-configured bot regardless of the incoming request.
   */
  public function resolveBot(Request $request): Bot
  {
    return $this->bot;
  }
}
