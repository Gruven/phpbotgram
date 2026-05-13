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

// ---------------------------------------------------------------------------

/**
 * Covers `StatesGroup::bootstrap()`, cascaded naming, membership tests,
 * idempotency, and `getRoot()` traversal.
 *
 * Mirrors upstream `StatesGroupMeta` semantics
 * (`aiogram/fsm/state.py:89-180`).
 *
 * NOTE: All fixture classes are bootstrapped in `setUpBeforeClass()` so
 * that the `$bootstrapped` idempotency guard is shared correctly across all
 * tests in this class (as it would be in a running application).
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

    self::assertTrue(FixtureForm::match($event, rawState: 'FixtureForm:name'));
    self::assertTrue(FixtureForm::match($event, rawState: 'FixtureForm.FixtureChild:email'));
  }

  /**
   * `match()` returns `false` when `rawState` is absent or unknown.
   */
  public function testMatchReturnsFalseForUnknownRawState(): void
  {
    $event = new stdClass();

    self::assertFalse(FixtureForm::match($event, rawState: 'unknown'));
    self::assertFalse(FixtureForm::match($event));
  }
}
