<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher\Middlewares;

use Closure;
use Gruven\PhpBotGram\Types\TelegramObject;

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
 * Note: dispatcher middlewares are intentionally distinct from request
 * middlewares (`Client/Session/Middleware/BaseRequestMiddleware`). Request
 * middlewares wrap outgoing API calls (`Bot`, `TelegramMethod`, `?int $timeout`);
 * dispatcher middlewares wrap incoming update routing (`TelegramObject`, kwargs).
 *
 * The contextual `$data` bag (`bot`, `event_context`, FSM `state`, …) MAY be
 * mutated before delegating — `CallableObject` will then bind only the keys
 * each concrete handler declares.
 */
abstract class BaseMiddleware
{
  /**
   * @param Closure(TelegramObject, array<string, mixed>): mixed $handler
   * @param array<string, mixed> $data
   */
  abstract public function __invoke(Closure $handler, TelegramObject $event, array $data): mixed;
}
