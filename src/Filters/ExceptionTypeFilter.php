<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Filters;

use Gruven\PhpBotGram\Types\ErrorEvent;
use InvalidArgumentException;
use Throwable;

/**
 * Filter that accepts an `ErrorEvent` whose `->exception` is an instance of
 * one of the registered exception class-strings.
 *
 * Port of `aiogram.filters.exception.ExceptionTypeFilter`
 * (`aiogram/filters/exception.py:10-27`).
 *
 * # Use case
 *
 * Wired onto the dispatcher's `errors` observer to route synthetic
 * `ErrorEvent`s to specific handlers based on the underlying throwable's
 * type. Mirrors upstream usage:
 *
 *     $dispatcher->errors->register(
 *         fn(ErrorEvent $e) => ...,
 *         filters: [new ExceptionTypeFilter(TelegramAPIError::class)],
 *     );
 *
 * # Return shape
 *
 * Pure `bool`. The filter contributes no kwargs to the handler — matching
 * upstream's `bool` return where every accept/reject decision is binary.
 * Composition with `ExceptionMessageFilter` (which contributes match
 * kwargs) goes through a Logic `AndFilter` combinator.
 */
final class ExceptionTypeFilter extends Filter
{
  /**
   * Registered exception class-strings to probe with `instanceof`. Held as
   * a `list<class-string<Throwable>>` so the loop order is predictable and
   * `array_values` in the constructor strips any string keys from variadic
   * unpacking.
   *
   * @var list<class-string<Throwable>>
   */
  public readonly array $exceptions;

  /**
   * @param class-string<Throwable> ...$exceptions Exception class-strings
   *                                               to match against `ErrorEvent->exception`. Must contain at least
   *                                               one entry; throws otherwise.
   */
  public function __construct(string ...$exceptions)
  {
    if ($exceptions === []) {
      // Upstream raises `ValueError('At least one exception type is required')`
      // at `aiogram/filters/exception.py:21-23`. PHP equivalent is
      // `InvalidArgumentException` — the closest semantic match in the
      // SPL hierarchy. Same rationale as `Command`'s empty-list guard.
      throw new InvalidArgumentException('At least one exception type is required');
    }

    // `array_values` strips any string keys variadic unpacking might
    // leave behind, guaranteeing the readonly property is a proper
    // `list<class-string<Throwable>>` for PHPStan level 9.
    $this->exceptions = array_values($exceptions);
  }

  /**
   * @param array<string, mixed> $kwargs Unused — the filter is event-only.
   */
  public function __invoke(object $event, array $kwargs = []): bool
  {
    if (!$event instanceof ErrorEvent) {
      // Defensive type guard. A misconfigured router could wire this
      // filter onto a non-errors observer; rejecting silently is safer
      // than crashing on `->exception` indirection. Mirrors the
      // `if !$event instanceof X` guard used by `Command` /
      // `CallbackQueryFilter` / `ChatMemberUpdatedFilter`.
      return false;
    }

    // Linear `instanceof` probe; short-circuits on the first match.
    // Mirrors upstream's `isinstance(event.exception, tuple_of_classes)`
    // which is also a linear walk under the hood. With small N (typically
    // 1–3 registered classes) the overhead is negligible.
    foreach ($this->exceptions as $class) {
      if ($event->exception instanceof $class) {
        return true;
      }
    }

    return false;
  }
}
