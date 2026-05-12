<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Throwable;

/**
 * Dispatcher-synthetic event raised when a registered Telegram handler throws
 * an exception that isn't an internal signalling marker (`SkipHandler` /
 * `CancelHandler`). Mirrors aiogram's `aiogram.types.error_event.ErrorEvent`.
 *
 * Built by `ErrorsMiddleware` from the offending `Update` and the original
 * `Throwable`, then forwarded to the dispatcher's `errors` observer so that
 * registered error handlers can claim the failure (returning a substitute
 * value), inspect it, or fall through and let the exception re-raise.
 *
 * This class is hand-authored — it is **not** produced by the Phase 2 codegen
 * because the schema doesn't describe it (`ErrorEvent` exists only in the
 * dispatcher domain, not in the Telegram Bot API wire format). To keep
 * `make regenerate` from clobbering this file, the relative path
 * `Types/ErrorEvent.php` is listed in
 * `\Gruven\PhpBotGram\Generator\FileEmitter::PROTECTED_PATHS`.
 *
 * Differences from upstream:
 *
 * 1. `exception` is typed as `Throwable` rather than `Exception`. PHP's `Error`
 *    hierarchy (e.g. `TypeError`, `DivisionByZeroError`) flows through the
 *    same `try`/`catch (Throwable)` path in `ErrorsMiddleware`, so the value
 *    object must accept both branches of the standard interface.
 * 2. The class extends nothing — upstream's `ErrorEvent` extends `TelegramObject`
 *    for pydantic serialisation, but our `TelegramObject` carries `Bot`
 *    plumbing that doesn't apply to a dispatcher-synthetic event. Keeping
 *    `ErrorEvent` as a standalone readonly value object avoids leaking
 *    `BotContextController` semantics into the error pipeline.
 */
final readonly class ErrorEvent
{
  public function __construct(
    public Update $update,
    public Throwable $exception,
  ) {}
}
