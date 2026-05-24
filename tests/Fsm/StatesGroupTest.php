<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm;

use Gruven\PhpBotGram\Fsm\State;
use Gruven\PhpBotGram\Fsm\StatesGroup;
use PHPUnit\Framework\TestCase;
use stdClass;

// ---------------------------------------------------------------------------
// Fixture classes (defined at file scope so CHILDREN const can reference them)
// ---------------------------------------------------------------------------

/**
 * Grandchild group — deepest level of the three-level fixture hierarchy.
 */
final class FixtureGrandChild extends StatesGroup
{
  public static State $city;
}

/**
 * Child group — middle level, contains one state and one grandchild.
 */
final class FixtureChild extends StatesGroup
{
  public static State $email;

  /** @var array<class-string<StatesGroup>> */
  public const array CHILDREN = [FixtureGrandChild::class];
}

/**
 * Root group — top-level fixture with two states and one child subtree.
 */
final class FixtureForm extends StatesGroup
{
  public static State $name;
  public static State $age;

  /** @var array<class-string<StatesGroup>> */
  public const array CHILDREN = [FixtureChild::class];
}

/**
 * A flat group with no children, used for isolated single-group tests.
 */
final class FixtureFlatGroup extends StatesGroup
{
  public static State $start;
  public static State $finish;
}

/**
 * Standalone child used to reproduce the idempotency-guard ordering bug:
 * `FixtureChildStandalone::bootstrap()` is called first (standalone), then
 * `FixtureParentStandalone::bootstrap()` is called — the child's
 * `fullGroupName` must reflect the parent prefix after the parent bootstrap.
 */
final class FixtureChildStandalone extends StatesGroup
{
  public static State $x;
}

/**
 * Parent for the ordering-bug regression test.
 */
final class FixtureParentStandalone extends StatesGroup
{
  /** @var array<class-string<StatesGroup>> */
  public const array CHILDREN = [FixtureChildStandalone::class];
}

// ---------------------------------------------------------------------------

/**
 * Upstream `tests/test_fsm/test_state.py` `TestStatesGroup` cases deliberately
 * not ported here (all others are ported below):
 *
 * - No deliberate skips. All `TestStatesGroup` upstream cases are ported
 *   in this file.
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 *
 * @internal
 */
final class StatesGroupTest extends TestCase
{
  public static function setUpBeforeClass(): void
  {
    FixtureForm::bootstrap();
    FixtureFlatGroup::bootstrap();
  }

  // ------------------------------------------------------------------ //
  // fullGroupName cascading
  // ------------------------------------------------------------------ //

  /**
   * Root group's `fullGroupName` is its short class name.
   */
  public function testRootFullGroupNameIsShortClassName(): void
  {
    self::assertSame('FixtureForm', FixtureForm::fullGroupName());
  }

  /**
   * Child group's `fullGroupName` is `Parent.Child`.
   */
  public function testChildFullGroupNameCascadesFromParent(): void
  {
    self::assertSame('FixtureForm.FixtureChild', FixtureChild::fullGroupName());
  }

  /**
   * Grandchild group's `fullGroupName` is `Root.Child.GrandChild`.
   */
  public function testGrandChildFullGroupNameCascadesTwoLevels(): void
  {
    self::assertSame('FixtureForm.FixtureChild.FixtureGrandChild', FixtureGrandChild::fullGroupName());
  }

  // ------------------------------------------------------------------ //
  // State name assignment
  // ------------------------------------------------------------------ //

  /**
   * Root group states have their names defaulted to the property name and
   * the group prefix set to the root's `fullGroupName`.
   */
  public function testRootStateNamesAreQualifiedWithGroupName(): void
  {
    self::assertSame('FixtureForm:name', FixtureForm::$name->state());
    self::assertSame('FixtureForm:age', FixtureForm::$age->state());
  }

  /**
   * Nested group state has the child group's qualified prefix.
   */
  public function testChildStateNameHasChildGroupPrefix(): void
  {
    self::assertSame('FixtureForm.FixtureChild:email', FixtureChild::$email->state());
  }

  /**
   * Grandchild state has the full three-level qualified prefix.
   */
  public function testGrandChildStateNameHasFullPrefix(): void
  {
    self::assertSame('FixtureForm.FixtureChild.FixtureGrandChild:city', FixtureGrandChild::$city->state());
  }

  // ------------------------------------------------------------------ //
  // allStates / allStateNames / allChildren
  // ------------------------------------------------------------------ //

  /**
   * `allStates()` on the root returns only DIRECT states (not children's).
   */
  public function testAllStatesReturnsDirectStatesOnly(): void
  {
    $states = FixtureForm::allStates();

    self::assertCount(2, $states);
    self::assertContains(FixtureForm::$name, $states);
    self::assertContains(FixtureForm::$age, $states);
  }

  /**
   * `allStateNames()` on the root includes states from all depths.
   */
  public function testAllStateNamesIncludesAllDepths(): void
  {
    $names = FixtureForm::allStateNames();

    self::assertContains('FixtureForm:name', $names);
    self::assertContains('FixtureForm:age', $names);
    self::assertContains('FixtureForm.FixtureChild:email', $names);
    self::assertContains('FixtureForm.FixtureChild.FixtureGrandChild:city', $names);
    self::assertCount(4, $names);
  }

  /**
   * `allChildren()` on the root includes both direct and transitive children.
   */
  public function testAllChildrenIncludesTransitiveChildren(): void
  {
    $children = FixtureForm::allChildren();

    self::assertContains(FixtureChild::class, $children);
    self::assertContains(FixtureGrandChild::class, $children);
    self::assertCount(2, $children);
  }

  // ------------------------------------------------------------------ //
  // contains()
  // ------------------------------------------------------------------ //

  /**
   * `contains(string)` is true for a state-name string in the group.
   */
  public function testContainsReturnsTrueForKnownStateName(): void
  {
    self::assertTrue(FixtureForm::contains('FixtureForm:name'));
  }

  /**
   * `contains(State)` is true for a `State` instance belonging to the group.
   */
  public function testContainsReturnsTrueForKnownStateInstance(): void
  {
    self::assertTrue(FixtureForm::contains(FixtureForm::$name));
  }

  /**
   * `contains(ChildClass)` is true for a direct child group.
   */
  public function testContainsReturnsTrueForDirectChildClass(): void
  {
    self::assertTrue(FixtureForm::contains(FixtureChild::class));
  }

  /**
   * `contains(GrandChildClass)` is true for a transitive child group.
   */
  public function testContainsReturnsTrueForTransitiveChildClass(): void
  {
    self::assertTrue(FixtureForm::contains(FixtureGrandChild::class));
  }

  /**
   * `contains(State)` is true for a `State` belonging to a direct child group.
   *
   * Upstream parity: `__contains__` checks `__all_states__` (recursive),
   * not `__states__` (direct only).
   */
  public function testContainsReturnsTrueForChildState(): void
  {
    self::assertTrue(FixtureForm::contains(FixtureChild::$email));
  }

  /**
   * `contains(State)` is true for a `State` belonging to a grandchild group.
   *
   * Three-level nesting: Root → Child → GrandChild.
   */
  public function testContainsReturnsTrueForGrandChildState(): void
  {
    self::assertTrue(FixtureForm::contains(FixtureGrandChild::$city));
  }

  /**
   * `contains(string)` is false for an unknown state name.
   */
  public function testContainsReturnsFalseForUnknownStateName(): void
  {
    self::assertFalse(FixtureForm::contains('nonexistent'));
  }

  /**
   * `contains(string)` is false for a state that belongs to a sibling group.
   */
  public function testContainsReturnsFalseForStateBelongingToUnrelatedGroup(): void
  {
    self::assertFalse(FixtureForm::contains('FixtureFlatGroup:start'));
  }

  // ------------------------------------------------------------------ //
  // bootstrap() idempotency
  // ------------------------------------------------------------------ //

  /**
   * Calling `bootstrap()` a second time on the same class is a no-op —
   * state count does not grow and state instances remain identical.
   */
  public function testBootstrapIsIdempotent(): void
  {
    // First call was done in setUpBeforeClass(). Do a second call here.
    FixtureForm::bootstrap();

    $names = FixtureForm::allStateNames();

    // Must still be 4 — not doubled.
    self::assertCount(4, $names);
  }

  /**
   * Calling `bootstrap()` twice preserves object identity of `State`
   * instances — the same instance is returned from the property.
   */
  public function testBootstrapPreservesStateInstanceIdentity(): void
  {
    $before = FixtureForm::$name;
    FixtureForm::bootstrap();
    $after = FixtureForm::$name;

    self::assertSame($before, $after);
  }

  // ------------------------------------------------------------------ //
  // getRoot()
  // ------------------------------------------------------------------ //

  /**
   * `getRoot()` on a root group returns itself.
   */
  public function testGetRootOnRootGroupReturnsSelf(): void
  {
    self::assertSame(FixtureForm::class, FixtureForm::getRoot());
  }

  /**
   * `getRoot()` on a child group returns the topmost ancestor.
   */
  public function testGetRootOnChildGroupReturnsTopAncestor(): void
  {
    self::assertSame(FixtureForm::class, FixtureChild::getRoot());
  }

  /**
   * `getRoot()` on a grandchild group returns the topmost ancestor.
   */
  public function testGetRootOnGrandChildGroupReturnsTopAncestor(): void
  {
    self::assertSame(FixtureForm::class, FixtureGrandChild::getRoot());
  }

  // ------------------------------------------------------------------ //
  // Iteration / iterable contract — test_iterable
  // ------------------------------------------------------------------ //

  /**
   * Iterating a `StatesGroup` yields its direct (non-nested) `State` instances.
   *
   * Mirrors upstream `TestStatesGroup::test_iterable`:
   * `assert set(Group) == {Group.x, Group.y}`.
   *
   * PHP port: `allStates()` returns the direct states collection.
   */
  public function testGroupAllStatesContainsDirectStates(): void
  {
    // FixtureFlatGroup has exactly two direct states: $start and $finish.
    $states = FixtureFlatGroup::allStates();

    self::assertCount(2, $states);
    self::assertContains(FixtureFlatGroup::$start, $states);
    self::assertContains(FixtureFlatGroup::$finish, $states);
  }

  // ------------------------------------------------------------------ //
  // match() filter — test_empty_filter, test_with_state_filter,
  // test_nested_group_filter (filter-style invocations)
  // ------------------------------------------------------------------ //

  /**
   * An empty group returns `false` for every `rawState` (nothing to match).
   *
   * Mirrors upstream `TestStatesGroup::test_empty_filter`.
   */
  public function testEmptyGroupMatchReturnsFalse(): void
  {
    // Bootstrap a one-off empty group inline so this test is self-contained.
    $emptyGroup = new class extends StatesGroup {};
    $emptyGroup::class::bootstrap();

    $event = new stdClass();
    self::assertFalse($emptyGroup::class::match($event, raw_state: 'anything'));
  }

  /**
   * A group with states matches only its own qualified names.
   *
   * Mirrors upstream `TestStatesGroup::test_with_state_filter`:
   *   - `MyGroup()(None, "MyGroup:state1")` → True
   *   - `MyGroup()(None, "MyGroup:state2")` → True
   *   - `MyGroup()(None, "MyGroup:state3")` → False
   */
  public function testMatchReturnsTrueOnlyForGroupOwnStateNames(): void
  {
    $event = new stdClass();

    // FixtureFlatGroup has $start ('FixtureFlatGroup:start') and $finish.
    self::assertTrue(FixtureFlatGroup::match($event, raw_state: 'FixtureFlatGroup:start'));
    self::assertTrue(FixtureFlatGroup::match($event, raw_state: 'FixtureFlatGroup:finish'));
    self::assertFalse(FixtureFlatGroup::match($event, raw_state: 'FixtureFlatGroup:unknown'));
  }

  /**
   * A nested group filter includes child states in the parent match but not
   * the converse.
   *
   * Mirrors upstream `TestStatesGroup::test_nested_group_filter`:
   *   - Parent matches own state → True
   *   - Parent matches child's state → True
   *   - Parent does NOT match non-existent state → False
   *   - Child group matches child state → True
   *   - Child group does NOT match parent-only state → False
   */
  public function testNestedGroupFilterBehavior(): void
  {
    $event = new stdClass();

    // FixtureForm contains FixtureForm:name, FixtureForm:age, and all child states.
    self::assertTrue(FixtureForm::match($event, raw_state: 'FixtureForm:name'));
    self::assertTrue(FixtureForm::match($event, raw_state: 'FixtureForm.FixtureChild:email'));
    self::assertFalse(FixtureForm::match($event, raw_state: 'FixtureForm:nonexistent'));

    // FixtureChild matches its own states but not FixtureForm-only states.
    self::assertTrue(FixtureChild::match($event, raw_state: 'FixtureForm.FixtureChild:email'));
    self::assertFalse(FixtureChild::match($event, raw_state: 'FixtureForm:name'));
  }

  // ------------------------------------------------------------------ //
  // bootstrapIfNeeded()
  // ------------------------------------------------------------------ //

  /**
   * `bootstrapIfNeeded()` bootstraps a fresh group exactly once.
   */
  public function testBootstrapIfNeededBootstrapsFreshGroup(): void
  {
    FixtureFlatGroup::bootstrapIfNeeded();

    self::assertSame('FixtureFlatGroup:start', FixtureFlatGroup::$start->state());
    self::assertSame('FixtureFlatGroup:finish', FixtureFlatGroup::$finish->state());
  }

  /**
   * `bootstrapIfNeeded()` called a second time is harmless and does not
   * double-register states.
   */
  public function testBootstrapIfNeededIsIdempotent(): void
  {
    FixtureFlatGroup::bootstrapIfNeeded();
    FixtureFlatGroup::bootstrapIfNeeded();

    self::assertCount(2, FixtureFlatGroup::allStateNames());
  }

  // ------------------------------------------------------------------ //
  // match() (filter-style invocation)
  // ------------------------------------------------------------------ //

  /**
   * `match()` returns `true` when `rawState` is in the group's state-names.
   */
  public function testMatchReturnsTrueForKnownRawState(): void
  {
    $event = new stdClass();

    self::assertTrue(FixtureForm::match($event, raw_state: 'FixtureForm:name'));
    self::assertTrue(FixtureForm::match($event, raw_state: 'FixtureForm.FixtureChild:email'));
  }

  /**
   * `match()` returns `false` when `rawState` is absent or unknown.
   */
  public function testMatchReturnsFalseForUnknownRawState(): void
  {
    $event = new stdClass();

    self::assertFalse(FixtureForm::match($event, raw_state: 'unknown'));
    self::assertFalse(FixtureForm::match($event));
  }

  // ------------------------------------------------------------------ //
  // Bootstrap ordering — child-standalone-then-parent
  // ------------------------------------------------------------------ //

  /**
   * When a child is bootstrapped standalone before its parent, a subsequent
   * parent bootstrap must retroactively update the child's parent link so
   * that `fullGroupName()` and `State::state()` return the fully-qualified
   * names rather than the standalone (unqualified) ones.
   *
   * Regression test for the idempotency-guard ordering bug:
   * `ChildFix::bootstrap()` first → then `ParentFix::bootstrap()` → child
   * must report `'ParentFix.ChildFix:x'`, not `'ChildFix:x'`.
   */
  public function testChildBootstrappedStandaloneThenParentLinksProperly(): void
  {
    // Bootstrap child standalone first — replicates the bug scenario.
    FixtureChildStandalone::bootstrap();

    self::assertSame('FixtureChildStandalone:x', FixtureChildStandalone::$x->state());

    // Now bootstrap the parent that declares FixtureChildStandalone as a child.
    FixtureParentStandalone::bootstrap();

    // After the parent bootstrap, the child's qualified name must include
    // the parent prefix.
    self::assertSame('FixtureParentStandalone.FixtureChildStandalone:x', FixtureChildStandalone::$x->state());
    self::assertSame('FixtureParentStandalone.FixtureChildStandalone', FixtureChildStandalone::fullGroupName());

    // The parent's allStateNames must include the child's updated qualified name.
    self::assertContains(
      'FixtureParentStandalone.FixtureChildStandalone:x',
      FixtureParentStandalone::allStateNames(),
    );
  }
}
