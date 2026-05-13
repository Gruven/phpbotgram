<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Filters;

use Gruven\PhpBotGram\Filters\Logic\AndFilter;
use Gruven\PhpBotGram\Filters\Logic\InvertFilter;
use Gruven\PhpBotGram\Filters\Logic\OrFilter;
use Gruven\PhpBotGram\Types\TelegramObject;

/**
 * Abstract base for every dispatcher-side filter. Concrete subclasses
 * (Command, StateFilter, ChatMemberUpdatedFilter, the Logic combinators
 * below, the F-DSL builders, …) implement `__invoke` to vote on whether
 * a handler should run for a given Telegram update.
 *
 * A filter return is interpreted by `HandlerObject::check` exactly as in
 * upstream `aiogram.dispatcher.event.handler.HandlerObject.check` (lines
 * 114-123 of `dispatcher/event/handler.py`):
 *
 * - `false` — reject this handler; the dispatcher moves on. Later
 *   filters in the same handler are NOT consulted.
 * - `true` — accept; the filter contributes no extra kwargs.
 * - `array<string, mixed>` — accept and merge the entries into the
 *   kwargs bag flowing into subsequent filters and the handler itself
 *   (this is how `Command` injects `CommandObject`, `Regex` injects
 *   capture groups, etc.).
 *
 * Mirrors upstream `aiogram.filters.base.Filter` (`aiogram/filters/base.py`).
 * Unlike upstream's `BaseMiddleware`, `Filter` is NOT a `BotContextController`
 * subclass — filters are dispatch-time predicates rather than Telegram
 * entities, so they don't need serializer or `withBot()` wiring.
 *
 * Spec note: upstream leaves the `__call__` signature unconstrained because
 * Python's `*args, **kwargs` plumbing lets every subclass declare its own
 * parameter shape and the reflection adapter binds named kwargs to the
 * declared names. The PHP port locks the abstract signature down because
 * PHP enforces signature compatibility on overrides: `__invoke(TelegramObject
 * $event, array $kwargs = [])` matches how `TelegramEventObserver::triggerCore`
 * actually invokes filters — `$event` is extracted from the kwargs bag
 * upstream and passed positionally, and the rest of the bag arrives in
 * `$kwargs`. Concrete filters that need to depend on specific kwargs read
 * them out of `$kwargs` by key (see `Logic\AndFilter`'s cascade for the
 * canonical pattern).
 *
 * @phpstan-type FilterResult bool|array<string, mixed>
 */
abstract class Filter
{
  /**
   * Evaluate the filter against an update.
   *
   * @param array<string, mixed> $kwargs Dispatcher context for this filter
   *                                     call: `bot`, `event_context`, FSM `state`, plus any kwargs that
   *                                     previous filters in the chain accumulated.
   *
   * @return array<string, mixed>|bool See class docblock for the
   *                                   interpretation contract.
   */
  abstract public function __invoke(TelegramObject $event, array $kwargs = []): array|bool;

  /**
   * Compose an AND across filters: every child must accept, kwargs
   * cascade. PHP equivalent of Python's `f1 & f2`.
   */
  public static function all(Filter ...$filters): AndFilter
  {
    return new AndFilter(...$filters);
  }

  /**
   * Compose an OR across filters: the first accepting child wins, no
   * cascade. PHP equivalent of Python's `f1 | f2`.
   */
  public static function any(Filter ...$filters): OrFilter
  {
    return new OrFilter(...$filters);
  }

  /**
   * Invert a filter's accept/reject decision. Named `invertOf` rather
   * than `not` because PHP forbids a static and an instance method
   * sharing one name in a single class (the instance-side `$f->not()`
   * convenience may land in a later task).
   */
  public static function invertOf(Filter $filter): InvertFilter
  {
    return new InvertFilter($filter);
  }
}
