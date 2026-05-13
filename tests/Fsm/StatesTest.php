<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm;

use Gruven\PhpBotGram\Fsm\State;
use Gruven\PhpBotGram\Fsm\States;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Covers `States::default()` and `States::any()` — the PHP equivalents of
 * the upstream `default_state` and `any_state` module-level constants
 * (`aiogram/fsm/state.py:180-182`).
 */
final class StatesTest extends TestCase
{
  // ------------------------------------------------------------------ //
  // States::default()
  // ------------------------------------------------------------------ //

  /**
   * `States::default()` returns a `State` instance.
   */
  public function testDefaultReturnStateInstance(): void
  {
    self::assertInstanceOf(State::class, States::default());
  }

  /**
   * `States::default()->state()` returns `null` — the no-state sentinel.
   *
   * Mirrors `default_state.state is None` (`aiogram/fsm/state.py:43`).
   */
  public function testDefaultStateReturnsNullFromStateMethod(): void
  {
    self::assertNull(States::default()->state());
  }

  /**
   * `States::default()` is a singleton — multiple calls return the same
   * instance.
   */
  public function testDefaultIsSingleton(): void
  {
    self::assertSame(States::default(), States::default());
  }

  /**
   * `States::default()->rawState()` returns `null`.
   */
  public function testDefaultRawStateIsNull(): void
  {
    self::assertNull(States::default()->rawState());
  }

  /**
   * `States::default()->__invoke` matches when there is no active state
   * (`rawState` is absent or `null`), because `null === null`.
   *
   * It does NOT match when a real qualified state string is provided.
   *
   * Mirrors upstream `default_state(event, raw_state=None) → True`
   * and `default_state(event, raw_state='Form:step') → False`
   * (`aiogram/fsm/state.py:60-68`).
   */
  public function testDefaultInvokeMatchesNullRawStateNotQualifiedString(): void
  {
    $event = new stdClass();

    // No active FSM state (rawState absent) → null === null → true.
    self::assertTrue(States::default()->__invoke($event));
    // Explicit null kwarg — same result.
    self::assertTrue(States::default()->__invoke($event, rawState: null));
    // A qualified state string does NOT match the null sentinel.
    self::assertFalse(States::default()->__invoke($event, rawState: 'Form:step'));
  }

  // ------------------------------------------------------------------ //
  // States::any()
  // ------------------------------------------------------------------ //

  /**
   * `States::any()` returns a `State` instance.
   */
  public function testAnyReturnStateInstance(): void
  {
    self::assertInstanceOf(State::class, States::any());
  }

  /**
   * `States::any()->state()` returns `'*'` — the any-state sentinel.
   *
   * Mirrors `any_state = State('*')` (`aiogram/fsm/state.py:182`).
   */
  public function testAnyStateReturnsAsteriskFromStateMethod(): void
  {
    self::assertSame('*', States::any()->state());
  }

  /**
   * `States::any()` is a singleton — multiple calls return the same
   * instance.
   */
  public function testAnyIsSingleton(): void
  {
    self::assertSame(States::any(), States::any());
  }

  /**
   * `States::any()->rawState()` returns `'*'`.
   */
  public function testAnyRawStateIsAsterisk(): void
  {
    self::assertSame('*', States::any()->rawState());
  }

  /**
   * `States::any()->__invoke($event)` returns `true` regardless of the
   * `rawState` kwarg — the any-state sentinel always matches.
   *
   * Mirrors `any_state.__call__` behaviour (`aiogram/fsm/state.py:63`).
   */
  public function testAnyInvokeReturnsTrueForAnyRawState(): void
  {
    $event = new stdClass();

    self::assertTrue(States::any()->__invoke($event, rawState: 'Form:step'));
    self::assertTrue(States::any()->__invoke($event, rawState: 'anything'));
    self::assertTrue(States::any()->__invoke($event, rawState: null));
    self::assertTrue(States::any()->__invoke($event));
  }

  // ------------------------------------------------------------------ //
  // default vs any differentiation
  // ------------------------------------------------------------------ //

  /**
   * The two singletons are distinct objects.
   */
  public function testDefaultAndAnyAreDifferentInstances(): void
  {
    self::assertNotSame(States::default(), States::any());
  }

  /**
   * `default` and `any` have different `state()` return values.
   */
  public function testDefaultAndAnyHaveDifferentStateValues(): void
  {
    self::assertNotSame(States::default()->state(), States::any()->state());
  }
}
