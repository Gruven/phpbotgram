<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Webhook\Server;

use Amp\Http\HttpStatus;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;

/**
 * Minimal single-route POST dispatcher.
 *
 * Installed by {@see AmphpServer::run()} because `amphp/http-server-router`
 * is not a project dependency.  If `amphp/http-server-router` is added in
 * the future, `AmphpServer::run()` can delegate to it directly and this class
 * can be removed.
 *
 * ## Route matching
 *
 * Supports two path forms:
 *
 * - **Exact** — e.g. `'/webhook'`: the incoming request path must match
 *   `$pattern` exactly (modulo a trailing slash).
 * - **Parameterised** — a single `{bot_token}` placeholder, e.g.
 *   `'/webhook/{bot_token}'`: the trailing segment can be anything;
 *   the match still requires the prefix to be correct.
 *
 * Any other path produces `404 Not Found`.
 * Any non-POST method on a matching path produces `405 Method Not Allowed`.
 *
 * @internal
 */
final class PathRouter implements RequestHandler
{
  /** Regex built from $pattern at construction. */
  private readonly string $regex;

  /**
   * @param string $pattern The URL path pattern to match (e.g.
   *                        `'/webhook'` or `'/webhook/{bot_token}'`).
   * @param RequestHandler $handler The handler to delegate matching POST
   *                                requests to.
   */
  public function __construct(
    private readonly string $pattern,
    private readonly RequestHandler $handler,
  ) {
    $this->regex = $this->buildRegex($pattern);
  }

  public function handleRequest(Request $request): Response
  {
    $path = $request->getUri()->getPath();

    // Normalise trailing slash for comparison purposes.
    $normPath = rtrim($path, '/') ?: '/';

    if (!preg_match($this->regex, $normPath)) {
      return new Response(HttpStatus::NOT_FOUND, [], 'Not Found');
    }

    if ($request->getMethod() !== 'POST') {
      return new Response(
        HttpStatus::METHOD_NOT_ALLOWED,
        ['Allow' => 'POST'],
        'Method Not Allowed',
      );
    }

    return $this->handler->handleRequest($request);
  }

  /** Return the pattern string this router was built for. */
  public function getPattern(): string
  {
    return $this->pattern;
  }

  // -------------------------------------------------------------------------
  // Private helpers
  // -------------------------------------------------------------------------

  /**
   * Convert a path pattern like `/webhook/{bot_token}` into a full-match
   * regex.
   *
   * Each `{name}` placeholder is replaced with a non-empty segment matcher
   * `[^/]+`.  Literal slashes and other regex-special characters in the
   * static parts are escaped.  The trailing slash of the *whole pattern*
   * (if present) is stripped before matching to normalise the comparison
   * (the request path is also normalised in `handleRequest`).
   */
  private function buildRegex(string $pattern): string
  {
    // Normalise the pattern by stripping any trailing slash (mirrors
    // the normPath normalisation in handleRequest).
    $pattern = rtrim($pattern, '/') ?: '/';

    // Split on {placeholder} tokens and escape static segments.
    $parts = preg_split('/(\{[^}]+\})/', $pattern, -1, \PREG_SPLIT_DELIM_CAPTURE);

    if ($parts === false) {
      $parts = [$pattern];
    }

    $regexParts = [];

    foreach ($parts as $part) {
      if (str_starts_with($part, '{') && str_ends_with($part, '}')) {
        // Placeholder — match any non-empty path segment.
        $regexParts[] = '[^/]+';
      } else {
        // Escape regex-special characters in the static part.
        // Do NOT strip slashes — they are path separators and must
        // appear verbatim in the regex (e.g. the '/' between
        // '/webhook' and '{bot_token}').
        $regexParts[] = preg_quote($part, '#');
      }
    }

    return '#^' . implode('', $regexParts) . '$#';
  }
}
