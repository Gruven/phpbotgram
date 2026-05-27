<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Abstract base class for a named group of related FSM states.
 *
 * Mirrors `aiogram.fsm.state.StatesGroup` (implemented via the
 * `StatesGroupMeta` metaclass in `aiogram/fsm/state.py:89-180`).
 *
 * # Why bootstrap instead of metaclass
 *
 * Python's `StatesGroupMeta.__new__` runs at class-definition time,
 * automatically discovering all `State` class attributes and nested
 * `StatesGroup` subclasses.  PHP has no equivalent metaclass hook — class
 * declaration is passive.  The PHP port uses **explicit bootstrap**: the
 * framework (or user code) must call `MyGroup::bootstrap()` (or rely on
 * the defensive `bootstrapIfNeeded()`) before using the group.
 *
 * # User API
 *
 * Subclasses declare states as **public static typed properties** and nested
 * groups via the `CHILDREN` class constant:
 *
 * ```php
 * class Form extends StatesGroup {
 *     public static State $name;
 *     public static State $age;
 *     public const array CHILDREN = [Sub::class];
 * }
 *
 * class Sub extends StatesGroup {
 *     public static State $email;
 * }
 *
 * Form::bootstrap();  // or let StateFilter call bootstrapIfNeeded()
 * ```
 *
 * After bootstrap:
 * - `Form::$name->state()` → `'Form:name'`
 * - `Sub::$email->state()` → `'Form.Sub:email'`
 * - `Form::contains('Form:name')` → `true`
 * - `Form::contains(Sub::class)` → `true`
 *
 * # Per-class static data storage
 *
 * PHP abstract classes share a single static property namespace with all
 * subclasses (late-static binding lets `static::$prop` target the concrete
 * subclass, but ONLY if `$prop` is declared in that subclass or redeclared
 * there).  Declaring per-class data as static properties on the abstract
 * base would cause all subclasses to share the same slot.
 *
 * The PHP port therefore stores all per-class bootstrap data in the single
 * static `$registry` array on the base class, keyed by concrete class name:
 *
 * ```
 * self::$registry[ClassName]['fullGroupName']  = '...';
 * self::$registry[ClassName]['allStates']      = [...];
 * self::$registry[ClassName]['allStateNames']  = [...];
 * self::$registry[ClassName]['allChildren']    = [...];
 * self::$registry[ClassName]['parentGroup']    = ClassName|null;
 * ```
 *
 * All accessors (`allStates()`, `fullGroupName()`, …) key into this registry
 * via `static::class`.  Because the registry and every accessor are on the
 * SAME abstract base class, PHPStan's `staticClassAccess.privateMethod` rule
 * is satisfied by calling them via `self::` (not `static::`): every call
 * site that dispatches via `self::` is within the same class where the
 * private method is declared.
 *
 * # Thread-safety / idempotency
 *
 * `bootstrap()` is idempotent: it tracks processed classes in the static
 * `$registry` keyed by class name.  Multiple calls to `bootstrap()` for
 * the same class are harmless.
 *
 * @see State
 * @see States for the module-level `default_state` / `any_state` equivalents.
 */
abstract class StatesGroup
{
  /**
   * Subclasses list nested `StatesGroup` subclasses here.
   *
   * ```php
   * public const array CHILDREN = [Sub::class, AnotherSub::class];
   * ```
   *
   * Bootstrap walks this list recursively so that nested groups acquire
   * the correct `fullGroupName()` cascade before their states are resolved.
   *
   * @var array<class-string<StatesGroup>>
   */
  public const array CHILDREN = [];

  /**
   * Per-class bootstrap data store.
   *
   * Keys at the first level are concrete subclass names.  Each entry holds:
   * - `'bootstrapped'`          → `true` (presence signals the class is done)
   * - `'fullGroupName'`         → `string`
   * - `'parentGroup'`           → `class-string<StatesGroup>|null`
   * - `'allStates'`             → `array<State>` (direct states only)
   * - `'allStateNames'`         → `array<string>` (direct + all nested)
   * - `'allChildren'`           → `array<class-string<StatesGroup>>`
   * - `'allStatesIncludingNested'` → `array<State>` (direct + all nested)
   *
   * @var array<class-string<StatesGroup>, array<string, mixed>>
   */
  private static array $registry = [];

  // ------------------------------------------------------------------ //
  // Bootstrap
  // ------------------------------------------------------------------ //

  /**
   * Walk the class via reflection to auto-vivify State instances, bind
   * names and parents, and recurse into children.
   *
   * Idempotent: repeated calls for the same class are a no-op.
   * When a parent bootstraps a child that is already in the registry the
   * child's `parentGroup` is updated so that `fullGroupName()` can walk
   * the correct chain on the next call.
   *
   * Mirrors `StatesGroupMeta.__new__` (`aiogram/fsm/state.py:89-143`).
   *
   * @param null|class-string<StatesGroup> $parentClass When called recursively
   *                                                    from a parent group,
   *                                                    this is the parent's
   *                                                    class name.
   */
  public static function bootstrap(
    ?string $parentClass = null,
  ): void {
    $class = static::class;

    // Idempotency guard.
    if (isset(self::$registry[$class])) {
      return;
    }

    // Reserve the entry immediately so recursive CHILDREN calls cannot
    // trigger a second top-level bootstrap for this class.
    self::$registry[$class] = [];

    // -- Resolve shortName (stable) and parentGroup (may be updated later) --
    $shortName = self::extractShortName($class);

    self::$registry[$class]['shortName'] = $shortName;
    self::$registry[$class]['parentGroup'] = $parentClass;

    // fullGroupName is derived dynamically in fullGroupName() — not cached.

    // -- Discover State static properties ------------------------------
    $rc = new ReflectionClass($class);
    $states = [];

    foreach ($rc->getProperties(ReflectionProperty::IS_STATIC | ReflectionProperty::IS_PUBLIC) as $prop) {
      if ($prop->class !== $class) {
        // Skip properties inherited from StatesGroup base or other ancestors.
        continue;
      }

      $type = $prop->getType();

      if (!$type instanceof ReflectionNamedType) {
        continue;
      }

      if ($type->getName() !== State::class) {
        continue;
      }

      // Auto-vivify when the property was declared but not yet initialised.
      if (!$prop->isInitialized(null)) {
        $prop->setValue(null, new State());
      }

      /** @var State $state */
      $state = $prop->getValue(null);

      // Framework-internal setters — idempotent on repeat calls.
      $state->setName($prop->getName());

      /** @var class-string<StatesGroup> $class */
      $state->setParent($class);

      $states[] = $state;
    }

    self::$registry[$class]['allStates'] = $states;

    // -- Recurse into children -----------------------------------------
    /** @var array<class-string<StatesGroup>> $childClasses */
    $childClasses = static::CHILDREN;
    $allChildClasses = [];
    $allNestedStates = $states;

    foreach ($childClasses as $childClass) {
      if (isset(self::$registry[$childClass])) {
        // Child was already bootstrapped standalone — update its parentGroup so
        // that fullGroupName() can now walk the correct parent chain.
        self::$registry[$childClass]['parentGroup'] = $class;
      } else {
        $childClass::bootstrap(parentClass: $class);
      }

      $allChildClasses[] = $childClass;
      $allChildClasses = array_merge($allChildClasses, $childClass::allChildren());
      $allNestedStates = array_merge($allNestedStates, $childClass::allStatesIncludingNested());
    }

    self::$registry[$class]['allChildren'] = $allChildClasses;
    self::$registry[$class]['allStatesIncludingNested'] = $allNestedStates;
    // allStateNames is derived dynamically in allStateNames() — not cached.
  }

  /**
   * Call `bootstrap()` exactly once per class; subsequent calls are no-ops.
   *
   * Framework-defensive entry point — called by `StateFilter` before reading
   * any group metadata, so that user code that omits the explicit `bootstrap()`
   * call still works.
   */
  public static function bootstrapIfNeeded(): void
  {
    if (!isset(self::$registry[static::class])) {
      static::bootstrap();
    }
  }

  // ------------------------------------------------------------------ //
  // Static accessors (post-bootstrap)
  // ------------------------------------------------------------------ //

  /**
   * Return all `State` instances directly belonging to this group.
   *
   * Mirrors `StatesGroup.__iter__` (`aiogram/fsm/state.py:155-157`).
   *
   * @return array<State>
   */
  public static function allStates(): array
  {
    /** @var array<State> */
    return self::$registry[static::class]['allStates'] ?? [];
  }

  /**
   * Return all `State` instances belonging to this group and all nested children.
   *
   * Mirrors `StatesGroup.__all_states__` (`aiogram/fsm/state.py:77, 135-139`),
   * which recursively includes states from all child groups.
   *
   * @return array<State>
   */
  public static function allStatesIncludingNested(): array
  {
    /** @var array<State> */
    return self::$registry[static::class]['allStatesIncludingNested'] ?? [];
  }

  /**
   * Return all qualified state-name strings for this group and its children.
   *
   * Derived on each call from the live `State` objects in
   * `allStatesIncludingNested()` so that a child whose `parentGroup` was
   * updated after standalone bootstrap emits the correct qualified names.
   *
   * Mirrors `StatesGroup.__all_states_names__` (`aiogram/fsm/state.py:126-131`).
   *
   * @return array<string>
   */
  public static function allStateNames(): array
  {
    $names = [];

    foreach (static::allStatesIncludingNested() as $state) {
      $qualified = $state->state();

      if ($qualified !== null) {
        $names[] = $qualified;
      }
    }

    return $names;
  }

  /**
   * Return all direct + transitive child `StatesGroup` class names.
   *
   * @return array<class-string<StatesGroup>>
   */
  public static function allChildren(): array
  {
    /** @var array<class-string<StatesGroup>> */
    return self::$registry[static::class]['allChildren'] ?? [];
  }

  /**
   * Return the fully-qualified group name (`Parent.Child.GrandChild`).
   *
   * Computed dynamically by walking the `parentGroup` chain so that a child
   * bootstrapped standalone and later claimed by a parent reflects the correct
   * qualified prefix on the very next call — no stale cached value.
   *
   * Mirrors `StatesGroup.__full_group_name__` (`aiogram/fsm/state.py:117-120`).
   */
  public static function fullGroupName(): string
  {
    $class = static::class;
    static::bootstrapIfNeeded();
    $entry = self::$registry[$class];

    /** @var null|class-string<StatesGroup> $parent */
    $parent = $entry['parentGroup'] ?? null;
    $shortName = is_string($entry['shortName'] ?? null) ? $entry['shortName'] : '';

    if ($parent !== null) {
      return $parent::fullGroupName() . '.' . $shortName;
    }

    return $shortName;
  }

  /**
   * Walk the `parentGroup` chain to the topmost group and return its class.
   *
   * Mirrors `StatesGroup.get_root()` (`aiogram/fsm/state.py:160-168`).
   *
   * @return class-string<StatesGroup>
   */
  public static function getRoot(): string
  {
    $current = static::class;

    while (true) {
      /** @var class-string<StatesGroup> $current */
      $parent = self::$registry[$current]['parentGroup'] ?? null;

      if (!is_string($parent)) {
        return $current;
      }

      $current = $parent;
    }
  }

  /**
   * Test membership of a string state name, a `State` instance, or a
   * `StatesGroup` subclass.
   *
   * - `string` — checks `allStateNames()`.
   * - `State`  — checks `allStates()`.
   * - class string — checks `allChildren()`.
   *
   * Mirrors `StatesGroup.__contains__` (`aiogram/fsm/state.py:145-153`).
   *
   * @param class-string<StatesGroup>|State|string $item
   */
  public static function contains(State|string $item): bool
  {
    if ($item instanceof State) {
      return in_array($item, static::allStatesIncludingNested(), strict: true);
    }

    // Could be a state-name string or a StatesGroup class-string.
    if (is_subclass_of($item, self::class)) {
      return in_array($item, static::allChildren(), strict: true);
    }

    return in_array($item, static::allStateNames(), strict: true);
  }

  // ------------------------------------------------------------------ //
  // Filter / callable contract
  // ------------------------------------------------------------------ //

  /**
   * Evaluate whether the event is in any state belonging to this group.
   *
   * Reads `$kwargs['raw_state'] ?? null` (snake_case, matching
   * `FsmContextMiddleware::RAW_STATE_KEY`) and returns `true` when that
   * value is in `allStateNames()`.
   *
   * Mirrors `StatesGroup.__call__` (`aiogram/fsm/state.py:154-157`).
   *
   * @return array<string, mixed>|bool
   */
  public static function match(object $event, mixed ...$kwargs): array|bool
  {
    $rawState = $kwargs['raw_state'] ?? null;

    if (!is_string($rawState)) {
      return false;
    }

    return in_array($rawState, static::allStateNames(), strict: true);
  }

  // ------------------------------------------------------------------ //
  // Internal helpers
  // ------------------------------------------------------------------ //

  /**
   * Return the short (unqualified) class name.
   *
   * `Foo\Bar\MyGroup` → `'MyGroup'`.
   *
   * @param class-string $class
   */
  private static function extractShortName(string $class): string
  {
    $parts = explode('\\', $class);

    return end($parts) ?: $class;
  }
}
