<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Filters;

use Gruven\PhpBotGram\Fsm\State;
use Gruven\PhpBotGram\Fsm\StatesGroup;
use InvalidArgumentException;

/**
 * Filter that matches a Telegram event against one or more FSM states.
 *
 * Mirrors `aiogram.filters.state.StateFilter` (`aiogram/filters/state.py`).
 *
 * # Accepted state types
 *
 * Each positional argument to the constructor may be:
 * - `null`                        — matches when `rawState` is `null` (user has
 *                                   no active state).
 * - `'*'`                         — wildcard; always matches regardless of `rawState`.
 * - A plain state-name string     — matches when `rawState === $string`.
 * - A class-string<StatesGroup>   — detected at runtime via `class_exists` +
 *                                   `is_subclass_of`; bootstrapped defensively
 *                                   then delegated to via `StatesGroup::match()`.
 * - A `State` instance            — delegates to `State::__invoke`.
 * - A `StatesGroup` instance      — delegates to `StatesGroup::match()`.
 *
 * # Defensive bootstrapping
 *
 * `StateFilter` calls `StatesGroup::bootstrapIfNeeded()` before any group look-up
 * so that user code which omits an explicit `MyGroup::bootstrap()` call still
 * produces correctly qualified state names.  `bootstrapIfNeeded()` is idempotent
 * — calling it on an already-bootstrapped group is a no-op.
 *
 * For `State` instances the defensive bootstrap is triggered when the State
 * belongs to a group that was bootstrapped as part of processing the current
 * filter invocation (e.g. when the group class-string appears alongside the
 * State instance in the constructor).  Passing the group's class-string as one
 * of the states is the idiomatic way to ensure the group is bootstrapped;
 * `StateFilter` also accepts a `StatesGroup` instance for the same purpose.
 *
 * # Reading `raw_state`
 *
 * `FsmContextMiddleware` injects the current FSM state into the kwargs bag as
 * `raw_state` (snake_case, matching `FsmContextMiddleware::RAW_STATE_KEY` and
 * the rest of the dispatcher's snake_case kwarg convention).
 * `StateFilter` reads `$kwargs['raw_state'] ?? null` — an absent key is
 * treated as `null` (no active state).
 *
 * @see State
 * @see StatesGroup
 */
final class StateFilter extends Filter
{
  /**
   * @var list<null|State|StatesGroup|string>
   */
  public readonly array $states;

  /**
   * @param null|State|StatesGroup|string ...$states One or more states to match
   *                                                 against. Strings may be plain
   *                                                 state names, `'*'`, or
   *                                                 class-strings of `StatesGroup`
   *                                                 subclasses (detected at runtime).
   */
  public function __construct(null|State|StatesGroup|string ...$states)
  {
    if ($states === []) {
      throw new InvalidArgumentException('At least one state is required.');
    }

    // `array_values` collapses the variadic array (which PHPStan considers
    // `array<int|string, ...>` due to named-arg spread) into a proper `list`.
    $this->states = array_values($states);
  }

  /**
   * Evaluate whether the event matches any of the registered states.
   *
   * Reads `$kwargs['raw_state'] ?? null` (injected by FsmContextMiddleware).
   * Returns `true` on the first match, `false` if no state matched.
   */
  public function __invoke(object $event, mixed ...$kwargs): bool
  {
    $rawStateRaw = array_key_exists('raw_state', $kwargs) ? $kwargs['raw_state'] : null;
    $rawState = is_string($rawStateRaw) ? $rawStateRaw : null;

    foreach ($this->states as $state) {
      // ---- null sentinel ------------------------------------------------
      if ($state === null) {
        if ($rawState === null) {
          return true;
        }

        continue;
      }

      // ---- State instance -----------------------------------------------
      if ($state instanceof State) {
        if ($state($event, raw_state: $rawState)) {
          return true;
        }

        continue;
      }

      // ---- StatesGroup instance -----------------------------------------
      if ($state instanceof StatesGroup) {
        $groupClass = $state::class;
        $groupClass::bootstrapIfNeeded();

        if ($groupClass::match($event, raw_state: $rawState)) {
          return true;
        }

        continue;
      }

      // ---- string branch ------------------------------------------------
      // May be: '*', a plain state-name string, or a class-string<StatesGroup>.

      // class-string<StatesGroup> detection (Option A from spec).
      if (class_exists($state) && is_subclass_of($state, StatesGroup::class)) {
        $state::bootstrapIfNeeded();

        if ($state::match($event, raw_state: $rawState)) {
          return true;
        }

        continue;
      }

      // '*' wildcard.
      if ($state === '*') {
        return true;
      }

      // Plain state-name string: exact comparison.
      if ($rawState === $state) {
        return true;
      }
    }

    return false;
  }
}
