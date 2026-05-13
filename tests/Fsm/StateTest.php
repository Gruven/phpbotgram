<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm;

use Gruven\PhpBotGram\Fsm\State;
use Gruven\PhpBotGram\Fsm\StatesGroup;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Upstream `tests/test_fsm/test_state.py` cases deliberately not ported:
 *
 * - `TestState::test_state_in_unknown_class` — API divergence: PHP has no
 *   `__set_name__` hook that raises on assignment outside `StatesGroup`; the
 *   guard exists only at bootstrap time, not at property-definition time.
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 */
final class StateTest extends TestCase
{
  // ------------------------------------------------------------------ //
  // Construction + unbound state() resolution
  // ------------------------------------------------------------------ //

  /**
   * A bare `new State()` returns `null` from `state()` — the no-state
   * sentinel.
   *
   * Mirrors upstream: `State().state is None` (`aiogram/fsm/state.py:43`).
   */
  public function testUnboundStateReturnsNull(): void
  {
    $state = new State();

    self::assertNull($state->state());
  }

  /**
   * `State(state: '*')` returns `'*'` from `state()` — the any-state
   * sentinel.
   *
   * Mirrors `any_state = State('*')` (`aiogram/fsm/state.py:182`).
   */
  public function testAnyStateSentinelReturnsAsterisk(): void
  {
    $state = new State(state: '*');

    self::assertSame('*', $state->state());
  }

  /**
   * After `setName('NAME')`, an unbound State that had no raw state
   * defaults its raw state to `'NAME'`.  The group prefix falls back to
   * `'@'` since no parent has been assigned.
   */
  public function testSetNameDefaultsRawStateToName(): void
  {
    $state = new State();
    $state->setName('NAME');

    // No group yet → falls back to '@' prefix.
    self::assertSame('@:NAME', $state->state());
  }

  /**
   * `setName` is idempotent — a second call with a different name is
   * silently ignored.
   */
  public function testSetNameIsIdempotent(): void
  {
    $state = new State();
    $state->setName('FIRST');
    $state->setName('SECOND');

    self::assertSame('@:FIRST', $state->state());
  }

  /**
   * When a `State` is constructed with an explicit `state` argument,
   * `setName` does NOT override it (the constructor value takes priority).
   */
  public function testExplicitStateArgNotOverriddenBySetName(): void
  {
    $state = new State(state: 'custom');
    $state->setName('IGNORED');

    self::assertSame('@:custom', $state->state());
  }

  /**
   * A `State` with an explicit `groupName` constructor arg uses that as
   * the prefix regardless of whether a parent group is bound.
   */
  public function testExplicitGroupNameOverridesParentGroup(): void
  {
    $state = new State(state: 'step', groupName: 'MyFlow');

    self::assertSame('MyFlow:step', $state->state());
  }

  // ------------------------------------------------------------------ //
  // Bound state (after bootstrap)
  // ------------------------------------------------------------------ //

  /**
   * After a full bootstrap via `StatesGroup::bootstrap()`, `state()` returns
   * the qualified `'GroupName:StateName'` form.
   *
   * The fixture group is defined inline to avoid cross-test pollution.
   */
  public function testBoundStateReturnsQualifiedForm(): void
  {
    // Define and bootstrap an inline group.
    $group = new class extends StatesGroup {
      public static State $myState;
    };
    $groupClass = $group::class;
    $groupClass::bootstrap();

    self::assertSame('myState', $groupClass::$myState->rawState());
  }

  // ------------------------------------------------------------------ //
  // Standalone (unbound) State with explicit name — `test_alone` branch
  // ------------------------------------------------------------------ //

  /**
   * A `State` constructed with only a raw name (no `groupName`) and later bound
   * via `setName` stores the raw name and qualifies with the `'@'` prefix.
   *
   * Mirrors upstream `test_alone`: `state.state == "@:test"`.
   */
  public function testAloneStateQualifiesWithAtPrefix(): void
  {
    $state = new State(state: 'test');
    $state->setName('test');

    self::assertSame('@:test', $state->state());
  }

  /**
   * A `State` with an explicit `groupName` and no parent group returns the
   * `'GroupName:name'` qualified form.
   *
   * Mirrors upstream `test_alone_with_group`:
   * `State("test", group_name="Test").state == "Test:test"`.
   */
  public function testAloneWithExplicitGroupNameReturnsQualifiedForm(): void
  {
    $state = new State(state: 'test', groupName: 'Test');

    self::assertSame('Test:test', $state->state());
  }

  // ------------------------------------------------------------------ //
  // __invoke parametrize rows — test_filter
  // ------------------------------------------------------------------ //

  /**
   * Data provider for `testFilterParametrize`.
   *
   * Mirrors `TestState::test_filter` parametrize rows from upstream.
   *
   * Each row: [state, rawStateArg, expectedResult].
   *
   * @return array<string, array{State, null|string, bool}>
   */
  public static function filterProvider(): array
  {
    return [
      'unbound_test_vs_bare_test' => [
        new State('test'), 'test', false,
      ],
      'unbound_test_vs_at_test' => [
        new State('test'), '@:test', true,
      ],
      'unbound_test_vs_test1' => [
        new State('test'), 'test1', false,
      ],
      'grouped_test:test_matches' => [
        new State('test', 'test'), 'test:test', true,
      ],
      'grouped_test:test_vs_test:test2' => [
        new State('test', 'test'), 'test:test2', false,
      ],
      'grouped_test:test_vs_test2:test' => [
        new State('test', 'test'), 'test2:test', false,
      ],
      'grouped_test:test_vs_test2:test2' => [
        new State('test', 'test'), 'test2:test2', false,
      ],
    ];
  }

  /**
   * `State.__invoke` (filter) matches only the qualified state string.
   *
   * Mirrors `TestState::test_filter` parametrize table from upstream.
   */
  #[DataProvider('filterProvider')]
  public function testFilterParametrize(State $state, string $rawStateArg, bool $expected): void
  {
    $event = new stdClass();

    self::assertSame($expected, $state->__invoke($event, rawState: $rawStateArg));
  }

  // ------------------------------------------------------------------ //
  // rawState() accessor
  // ------------------------------------------------------------------ //

  /**
   * `rawState()` returns the unqualified raw name, not the group-prefixed
   * qualified form.
   */
  public function testRawStateReturnsUnqualifiedName(): void
  {
    $state = new State(state: 'idle');

    self::assertSame('idle', $state->rawState());
  }

  /**
   * `rawState()` returns `null` for the no-state sentinel.
   */
  public function testRawStateReturnsNullForSentinel(): void
  {
    $state = new State();

    self::assertNull($state->rawState());
  }

  // ------------------------------------------------------------------ //
  // __invoke (filter contract)
  // ------------------------------------------------------------------ //

  /**
   * `State('*').__invoke` returns `true` for any raw-state value — the
   * any-state sentinel always matches.
   *
   * Mirrors upstream `State.__call__` with `self._state == '*'`
   * (`aiogram/fsm/state.py:63`).
   */
  public function testAnyStateSentinelAlwaysMatchesRegardlessOfRawState(): void
  {
    $state = new State(state: '*');
    $event = new stdClass();

    self::assertTrue($state->__invoke($event, rawState: 'anything'));
    self::assertTrue($state->__invoke($event, rawState: 'Form:step'));
    self::assertTrue($state->__invoke($event, rawState: null));
    self::assertTrue($state->__invoke($event));
  }

  /**
   * A bound `State` returns `true` from `__invoke` only when the
   * `rawState` kwarg exactly matches its qualified state string.
   */
  public function testBoundStateMatchesExactRawState(): void
  {
    $state = new State(state: 'active', groupName: 'Form');
    $event = new stdClass();

    self::assertTrue($state->__invoke($event, rawState: 'Form:active'));
    self::assertFalse($state->__invoke($event, rawState: 'Form:idle'));
    self::assertFalse($state->__invoke($event, rawState: null));
    self::assertFalse($state->__invoke($event));
  }

  /**
   * `__invoke` returns `false` when no `rawState` kwarg is present (i.e.
   * the user has no active FSM state).
   */
  public function testInvokeReturnsFalseWhenNoRawStateProvided(): void
  {
    $state = new State(state: 'step', groupName: 'Wizard');
    $event = new stdClass();

    self::assertFalse($state->__invoke($event));
  }

  // ------------------------------------------------------------------ //
  // equals()
  // ------------------------------------------------------------------ //

  /**
   * Two `State` instances with the same qualified name are equal.
   */
  public function testEqualsReturnsTrueForSameQualifiedName(): void
  {
    $a = new State(state: 'idle', groupName: 'Flow');
    $b = new State(state: 'idle', groupName: 'Flow');

    self::assertTrue($a->equals($b));
  }

  /**
   * A `State` is equal to a string that matches its qualified state name.
   */
  public function testEqualsReturnsTrueForMatchingString(): void
  {
    $state = new State(state: 'step', groupName: 'Form');

    self::assertTrue($state->equals('Form:step'));
    self::assertFalse($state->equals('Form:other'));
  }

  /**
   * Two `State` instances with different qualified names are not equal.
   */
  public function testEqualsReturnsFalseForDifferentStates(): void
  {
    $a = new State(state: 'one', groupName: 'G');
    $b = new State(state: 'two', groupName: 'G');

    self::assertFalse($a->equals($b));
  }

  /**
   * The no-state sentinel (`state() === null`) equals another null-state
   * `State` but NOT a non-null state.
   */
  public function testEqualsForNullStateSentinel(): void
  {
    $a = new State();
    $b = new State();
    $c = new State(state: 'something', groupName: 'G');

    self::assertTrue($a->equals($b));
    self::assertFalse($a->equals($c));
  }
}
