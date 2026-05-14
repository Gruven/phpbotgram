<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Webhook\Server;

use Amp\Http\HttpStatus;
use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Gruven\PhpBotGram\Webhook\IpFilter;

/**
 * Middleware that enforces the {@see IpFilter} allowlist.
 *
 * Port of upstream `ip_filter_middleware` + `check_ip` from
 * `aiogram/webhook/aiohttp_server.py:43-78`.
 *
 * ## IP resolution order (mirrors `check_ip`)
 *
 * 1. `X-Forwarded-For` header (first value before any comma) — used when
 *    the server sits behind a proxy such as nginx.
 * 2. The remote address reported by the amphp `Client` attached to the
 *    request — the raw TCP peer address.
 *
 * Requests whose resolved IP is not in the allowlist receive `401 Unauthorized`
 * (matching upstream's `raise HTTPUnauthorized()`).
 *
 * @internal
 */
final class IpFilterMiddleware implements Middleware
{
  public function __construct(private readonly IpFilter $ipFilter) {}

  public function handleRequest(Request $request, RequestHandler $requestHandler): Response
  {
    [$ip, $accepted] = $this->checkIp($request);

    if (!$accepted) {
      return new Response(HttpStatus::UNAUTHORIZED, [], 'Unauthorized');
    }

    return $requestHandler->handleRequest($request);
  }

  // -------------------------------------------------------------------------
  // Private helpers
  // -------------------------------------------------------------------------

  /**
   * Resolve the originating IP from the request and check it against the
   * allowlist.
   *
   * Port of `check_ip(ip_filter, request)` at `aiohttp_server.py:43-55`.
   *
   * @return array{0: string, 1: bool} Tuple of [resolvedIp, accepted].
   */
  private function checkIp(Request $request): array
  {
    // 1. X-Forwarded-For — take the left-most (client) address.
    $forwarded = $request->getHeader('X-Forwarded-For') ?? '';

    if ($forwarded !== '') {
      $ip = trim(explode(',', $forwarded, 2)[0]);

      return [$ip, $this->ipFilter->check($ip)];
    }

    // 2. TCP peer address from the amphp Client.
    $remoteAddress = $request->getClient()->getRemoteAddress()->toString();

    // Strip the port suffix (e.g. '1.2.3.4:12345' → '1.2.3.4').
    // explode() always returns a non-empty list; the first element is the
    // host portion (possibly the full address when no colon is present,
    // e.g. a bare IPv4).
    $ip = explode(':', $remoteAddress, 2)[0];

    if ($ip === '') {
      return ['', false];
    }

    return [$ip, $this->ipFilter->check($ip)];
  }
}
