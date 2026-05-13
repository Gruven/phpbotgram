<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm;

/**
 * Module-level FSM state sentinels.
 *
 * Mirrors the two free constants defined at the bottom of
 * `aiogram/fsm/state.py`:
 *
 * ```python
 * default_state = State()       # no-state sentinel
 * any_state     = State('*')    # always-match sentinel
 * ```
 *
 * PHP cannot declare top-level object constants; instead, two static
 * singleton getters are provided so callers write `States::default()` and
 * `States::any()` rather than constructing the sentinels themselves.
 *
 * The instances are lazy-initialised on first access and reused thereafter.
 */
final class States
{
  /**
   * Lazily-initialised `default_state` singleton.
   */
  private static ?State $defaultInstance = null;

  /**
   * Lazily-initialised `any_state` singleton.
   */
  private static ?State $anyInstance = null;

  /**
   * Return the no-state sentinel (`default_state` in upstream).
   *
   * `state()` returns `null` because `$rawState` is `null`.
   */
  public static function default(): State
  {
    if (self::$defaultInstance === null) {
      self::$defaultInstance = new State();
    }

    return self::$defaultInstance;
  }

  /**
   * Return the always-match sentinel (`any_state` in upstream).
   *
   * `state()` returns `'*'`; `__invoke` always returns `true`.
   */
  public static function any(): State
  {
    if (self::$anyInstance === null) {
      self::$anyInstance = new State(state: '*');
    }

    return self::$anyInstance;
  }

  /**
   * Prevent instantiation — this class is a static namespace only.
   */
  private function __construct() {}
}
