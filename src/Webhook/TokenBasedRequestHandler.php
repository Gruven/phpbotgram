<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Webhook;

use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Closure;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use InvalidArgumentException;

/**
 * Multi-bot webhook handler that extracts the bot token from the URL path.
 *
 * Port of `aiogram.webhook.aiohttp_server.TokenBasedRequestHandler` (lines 249–305).
 *
 * ## URL pattern requirement
 *
 * The registered path **must** contain the literal placeholder `{bot_token}`
 * (snake_case, matching upstream), e.g. `/webhook/{bot_token}`.  The
 * `register()` method enforces this and throws `InvalidArgumentException`
 * when the placeholder is absent.
 *
 * ## Security caveat
 *
 * This handler is not recommended in production because the bot token is
 * available in the URL and can be logged by a reverse proxy server or other
 * middleware.  Use {@see SimpleRequestHandler} for single-bot deployments
 * where the token does not appear in the URL.
 *
 * ## Bot factory (deviation from upstream)
 *
 * Upstream accepts a `bot_settings` dict and spreads it into `Bot(token=…,
 * **bot_settings)`.  PHP does not allow `new Bot($token, ...$settings)` for
 * named-argument spread, so this port accepts a `Closure(string $token): Bot`
 * factory instead.  The factory receives the raw token extracted from the URL
 * and may apply any default session/parse-mode/etc configuration before
 * returning the `Bot` instance.  This is the PHP-idiomatic equivalent of
 * Python's `**kwargs`-spread construction pattern.
 *
 * ## Secret-token validation
 *
 * `verifySecret` always returns `true`.  In multi-bot mode, the URL-embedded
 * token is the authentication surface — there is no shared Telegram secret
 * token to validate against.
 *
 * ## Bot caching
 *
 * `resolveBot` lazily instantiates `Bot` objects via the factory on first use
 * for each token, then caches them for the lifetime of the handler.
 *
 * @internal
 */
final class TokenBasedRequestHandler extends BaseRequestHandler
{
  /**
   * Lazily-constructed Bot instances keyed by their token string.
   *
   * @var array<string, Bot>
   */
  private array $bots = [];

  /**
   * @param Closure(string $token): Bot $botFactory Factory that builds (and
   *                                                optionally configures) a Bot for a given token string.  Mirrors
   *                                                upstream's `bot_settings` dict that was spread into the Bot
   *                                                constructor — the factory carries the same information but is PHP-
   *                                                idiomatic.
   * @param array<string, mixed> $data Extra kwargs forwarded to feedWebhookUpdate.
   */
  public function __construct(
    Dispatcher $dispatcher,
    private readonly Closure $botFactory,
    bool $handleInBackground = true,
    array $data = [],
  ) {
    parent::__construct($dispatcher, $handleInBackground, $data);
  }

  /**
   * Always returns `true`.
   *
   * In multi-bot mode the URL-embedded token is the authentication surface;
   * there is no per-bot secret token to validate.
   *
   * @param string $telegramSecretToken The raw `X-Telegram-Bot-Api-Secret-Token` header value (ignored).
   * @param Bot $bot The resolved bot for this request (ignored).
   */
  public function verifySecret(string $telegramSecretToken, Bot $bot): bool
  {
    return true;
  }

  /**
   * Close every cached bot's underlying HTTP session and clear the cache.
   *
   * Called during webhook server shutdown.  Safe to call when no bots have
   * been resolved (no-op).
   */
  public function close(): void
  {
    foreach ($this->bots as $bot) {
      $bot->session->close();
    }

    $this->bots = [];
  }

  /**
   * Validate that `$path` contains the `{bot_token}` placeholder and register
   * this handler via the supplied routing callback.
   *
   * @param callable(string, RequestHandler): void $registerRoute Callback that
   *                                                              registers a POST route for the given path.
   * @param string $path URL path to bind; must contain `{bot_token}` (e.g.
   *                     `/webhook/{bot_token}`).
   *
   * @throws InvalidArgumentException When `$path` does not contain `{bot_token}`.
   */
  public function register(callable $registerRoute, string $path): void
  {
    if (!str_contains($path, '{bot_token}')) {
      throw new InvalidArgumentException(
        "Path should contain '{bot_token}' substring (got: '{$path}').",
      );
    }

    parent::register($registerRoute, $path);
  }

  /**
   * Resolve (or lazily create) the `Bot` instance for the token found in the
   * request URI.
   *
   * The `{bot_token}` segment is extracted from the URI path by matching
   * against the path structure.  Since `amphp/http-server-router` is not
   * bundled in this project, the token is read from the `bot_token` request
   * attribute when set (e.g. by a router middleware), or parsed from the URI
   * path as a fallback.
   *
   * @param Request $request The incoming HTTP request.
   *
   * @throws InvalidArgumentException When the `bot_token` attribute/path segment is missing or empty.
   */
  public function resolveBot(Request $request): Bot
  {
    // Prefer the request attribute set by a router (e.g. amphp/http-server-router).
    if ($request->hasAttribute('bot_token')) {
      $token = $request->getAttribute('bot_token');

      if (!is_string($token) || $token === '') {
        throw new InvalidArgumentException('bot_token path variable is missing or empty.');
      }

      return $this->bots[$token] ??= ($this->botFactory)($token);
    }

    // Fallback: extract the last non-empty path segment from the URI.
    // This works for paths like /webhook/{bot_token} where the token is
    // the trailing segment.
    $uriPath = $request->getUri()->getPath();
    $segments = array_filter(explode('/', $uriPath));
    $token = end($segments);

    if (!is_string($token) || $token === '') {
      throw new InvalidArgumentException('bot_token path variable is missing or empty.');
    }

    return $this->bots[$token] ??= ($this->botFactory)($token);
  }
}
