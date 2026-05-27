<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher\Middlewares;

use Closure;

/**
 * Abstract base class for dispatcher-side middlewares (mirrors aiogram's
 * `aiogram.dispatcher.middlewares.base.BaseMiddleware`).
 *
 * Concrete subclasses implement `__invoke` to participate in the
 * `(handler, event, data)` chain that wraps a registered Telegram update
 * handler: call `$handler($event, $data)` to delegate to the next link
 * (another middleware or, at the chain's tail, the resolved handler).
 * Skipping that call short-circuits the chain — useful for throttling,
 * auth gates, and cache hits.
 *
 * The `$event` parameter is typed as `object` rather than `TelegramObject`
 * because the same middleware pipeline transports synthetic events too —
 * notably `ErrorEvent`, which deliberately does NOT extend `TelegramObject`
 * (see `Types/ErrorEvent.php` for the rationale). Concrete middlewares may
 * declare a narrower type via covariance-safe `instanceof` checks; PHPStan
 * level 9 still flags wider unsafe accesses.
 *
 * Note: dispatcher middlewares are intentionally distinct from request
 * middlewares (`Client/Session/Middleware/BaseRequestMiddleware`). Request
 * middlewares wrap outgoing API calls (`Bot`, `TelegramMethod`, `?int $timeout`);
 * dispatcher middlewares wrap incoming update routing (`object`, kwargs).
 *
 * The contextual `$data` bag (`bot`, `event_context`, FSM `state`, …) MAY be
 * mutated before delegating — `CallableObject` will then bind only the keys
 * each concrete handler declares.
 */
abstract class BaseMiddleware
{
  /**
   * @param Closure(object, array<string, mixed>): mixed $handler
   * @param array<string, mixed> $data
   */
  abstract public function __invoke(Closure $handler, object $event, array $data): mixed;
}
