<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher\Middlewares;

use Closure;
use Gruven\PhpBotGram\Dispatcher\Event\RejectedSentinel;
use Gruven\PhpBotGram\Dispatcher\Event\UnhandledSentinel;
use Gruven\PhpBotGram\Types\ErrorEvent;
use Gruven\PhpBotGram\Types\Update;
use Throwable;

/**
 * Top-of-chain dispatcher middleware (mirror of aiogram's
 * `aiogram.dispatcher.middlewares.error.ErrorsMiddleware`) that **routes real
 * exceptions to the `errors` observer**.
 *
 *   Any `Throwable` is wrapped in an `ErrorEvent` and forwarded to the
 *   dispatcher's `errors` observer via the `$errorsTrigger` closure.
 *   - If the observer claims the error (returns a truthy non-sentinel value),
 *     that value becomes the middleware's return value.
 *   - If the observer returns `REJECTED`, the middleware collapses it to
 *     `UNHANDLED` (parity with upstream's `if response is REJECTED: return
 *     UNHANDLED`).
 *   - If the observer returns `UNHANDLED` or `null`, the original exception
 *     is re-raised so the caller sees it.
 *
 * **Signalling exceptions are NOT handled here.** `SkipHandlerException` and
 * `CancelHandlerException` are caught by `TelegramEventObserver::triggerCore`
 * at the per-handler boundary (Fix C1) so a `Bases::skip()` inside a handler
 * abandons that ONE handler and falls through to the next on the same
 * observer — parity with upstream's
 * `aiogram/dispatcher/event/telegram.py:127-128` per-handler `except
 * SkipHandler: continue`. The previous behaviour of catching them in this
 * top-of-chain middleware caused the WHOLE observer dispatch to collapse to
 * UNHANDLED on a single `Bases::skip()`, deviating from upstream's semantics.
 *
 * The trigger is wired by `Dispatcher::__construct`. The `event_update`
 * kwarg is injected by the Dispatcher (Task 3.10) before this middleware
 * runs — defensive code re-raises if it's absent or non-`Update`, because
 * the `errors` observer needs the original Update to filter on update shape.
 */
final class ErrorsMiddleware extends BaseMiddleware
{
  /**
   * Canonical name for the `Update` slot in the dispatcher's `$data` bag.
   * The Dispatcher (Task 3.10) writes the incoming `Update` to this key
   * before `ErrorsMiddleware` runs; the `errors` observer's filters bind
   * the same name via `CallableObject` reflection.
   */
  public const string EVENT_UPDATE_KEY = 'event_update';

  /**
   * @param ?Closure(string, ErrorEvent, array<string, mixed>): mixed $errorsTrigger
   *                                                                                 Trigger callable that invokes the dispatcher's `errors` observer.
   *                                                                                 Signature: `($updateTypeName, $errorEvent, $data) → mixed`. Task 3.8
   *                                                                                 wires this to the real `TelegramEventObserver`; for now callers may
   *                                                                                 pass `null` (the default), in which case every non-signalling
   *                                                                                 exception is re-raised without observer dispatch.
   */
  public function __construct(
    public readonly ?Closure $errorsTrigger = null,
  ) {}

  /**
   * @param Closure(object, array<string, mixed>): mixed $handler
   * @param array<string, mixed> $data
   */
  public function __invoke(Closure $handler, object $event, array $data): mixed
  {
    try {
      return $handler($event, $data);
    } catch (Throwable $e) {
      $update = $data[self::EVENT_UPDATE_KEY] ?? null;

      // Defensive: a missing or poisoned event_update slot means we cannot
      // build an ErrorEvent. Re-raise rather than swallow the failure or
      // crash on a TypeError in ErrorEvent::__construct. The same applies
      // when no observer trigger was wired in.
      if (!$update instanceof Update || $this->errorsTrigger === null) {
        throw $e;
      }

      $errorEvent = new ErrorEvent($update, $e);
      $response = ($this->errorsTrigger)('error', $errorEvent, $data);

      // REJECTED collapses to UNHANDLED so the chain treats both as "no
      // observer claimed the error" — upstream parity with
      // `if response is REJECTED: return UNHANDLED`.
      if ($response === RejectedSentinel::instance()) {
        return UnhandledSentinel::instance();
      }

      // UNHANDLED or null means the observer didn't claim the error; re-raise
      // the original exception so the caller sees it intact. `null` is a
      // PHP-specific extension: a no-op observer may legitimately return
      // null, and treating that as "claimed" would silently eat the error.
      if ($response === UnhandledSentinel::instance() || $response === null) {
        throw $e;
      }

      return $response;
    }
  }
}
