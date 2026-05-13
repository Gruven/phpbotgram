<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Filters;

use Gruven\PhpBotGram\Types\CallbackQuery;
use Gruven\PhpBotGram\Types\TelegramObject;
use InvalidArgumentException;
use LogicException;

/**
 * Dispatcher-side filter that bridges incoming `CallbackQuery` events to a
 * typed `CallbackData` subclass. Created via `MyCallbackData::filter()`,
 * not instantiated directly by user code (though direct construction is
 * supported for tests and Logic combinator wiring).
 *
 * Port of `aiogram.filters.callback_data.CallbackQueryFilter`
 * (`aiogram/filters/callback_data.py:152-194`).
 *
 * # Behavior
 *
 *   1. Reject when the event isn't a `CallbackQuery` or carries no `data`.
 *   2. Call `$callbackDataClass::unpack($data)`.
 *   3. On success → return `['callback_data' => $parsed]` so the parsed
 *      instance reaches the handler as the `$callback_data` kwarg. The
 *      kwarg name is the snake_case form mirroring upstream's
 *      `{"callback_data": callback_data}` return shape.
 *   4. On `unpack()` failure (prefix mismatch, arity mismatch, …) →
 *      collapse the exception to `false` so the dispatcher can move on
 *      to the next handler. Matches upstream's
 *      `except (TypeError, ValueError): return False`.
 *
 * # MagicFilter rule
 *
 * Upstream's `__init__(*, callback_data, rule)` accepts an optional
 * `MagicFilter` post-validation rule. Task 4.8 keeps the surface
 * parameter-less; the rule argument lands when Phase 4.5+ wires
 * `MagicData`/the `F`-DSL into the filter chain.
 */
final class CallbackQueryFilter extends Filter
{
  /**
   * @param class-string<CallbackData> $callbackDataClass The bound
   *                                                      subclass that drives `unpack()`. Pinned at construction so
   *                                                      `__invoke` can dispatch without re-resolving the target type.
   */
  public function __construct(
    public readonly string $callbackDataClass,
  ) {}

  /**
   * @param array<string, mixed> $kwargs
   *
   * @return array<string, mixed>|false
   */
  public function __invoke(TelegramObject $event, array $kwargs = []): array|bool
  {
    if (!$event instanceof CallbackQuery) {
      // Mirror upstream's type guard. A misconfigured observer could
      // route a message here; rejecting is safer than crashing the
      // dispatch loop with a TypeError deep inside `unpack`.
      return false;
    }

    $data = $event->data;

    if ($data === null || $data === '') {
      // `CallbackQuery::$data` is optional on the wire (the button
      // could carry `url` or `game_short_name` instead). Mirrors
      // upstream's `or not query.data` short-circuit.
      return false;
    }

    try {
      $parsed = ($this->callbackDataClass)::unpack($data);
    } catch (InvalidArgumentException|LogicException) {
      // `InvalidArgumentException` covers prefix mismatch and
      // separator-in-value defects; `LogicException` covers arity
      // mismatch and undecodable target types. Both upstream
      // ValueError-equivalents collapse to a graceful `false`.
      return false;
    }

    return ['callback_data' => $parsed];
  }
}
