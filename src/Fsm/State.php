<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm;

/**
 * Represents a single FSM state value with optional group association.
 *
 * Mirrors `aiogram.fsm.state.State` (`aiogram/fsm/state.py:1-85`).
 *
 * # Upstream vs PHP differences
 *
 * Python's `State` relies on the `__set_name__` PEP 487 hook to bind the
 * attribute name when the class is defined and on `StatesGroupMeta` to set the
 * parent group. PHP has neither mechanism: class declaration is passive and
 * constants/static properties are not observed by the runtime. The PHP port
 * therefore uses explicit **bootstrap** via `StatesGroup::bootstrap()`:
 *
 * - `setName(string)` — called by `StatesGroup::bootstrap()` to default the
 *   raw state to the property name (mirrors `__set_name__`).
 * - `setParent(string)` — called by `StatesGroup::bootstrap()` to bind the
 *   owning group class (mirrors `set_parent(group)`).
 *
 * Both setters are idempotent after the first call; subsequent calls are
 * silently ignored so that bootstrap can be called multiple times safely.
 *
 * # Qualified state name
 *
 * `state()` returns:
 * - `null`  — when `$rawState` is null (unbound or no-state sentinel).
 * - `'*'`   — when `$rawState === '*'` (any-state sentinel).
 * - `'<group>:<name>'` — otherwise, where `<group>` is resolved from
 *   (in priority): an explicit `$groupName` constructor arg → the owning
 *   group's `fullGroupName()` → the bare fallback `'@'`.
 *
 * # Filter contract
 *
 * `__invoke` matches the `Filter::__invoke` signature
 * `(object $event, mixed ...$kwargs): bool|array`. State reads
 * `$kwargs['rawState'] ?? null` and:
 * - returns `true`  when `$rawState === '*'` (always matches).
 * - returns `true`  when the raw state string equals `$this->state()`.
 * - returns `false` otherwise.
 *
 * Mirrors `State.__call__` (`aiogram/fsm/state.py:60-68`).
 *
 * @see StatesGroup for the bootstrap mechanism.
 * @see States for the module-level `default_state` and `any_state` equivalents.
 */
class State
{
  /**
   * The raw (unqualified) state name as supplied at construction or via
   * `setName()`.  `null` means the no-state sentinel (default_state).
   * `'*'` means the any-state sentinel.
   */
  private ?string $rawState;

  /**
   * Optional explicit group-name prefix.  When provided it overrides the
   * parent group's `fullGroupName()` when building the qualified name.
   */
  private ?string $groupName;

  /**
   * The owning `StatesGroup` subclass, set by `setParent()` at bootstrap.
   *
   * @var null|class-string<StatesGroup>
   */
  private ?string $group = null;

  /**
   * Whether `setName` has already been called (idempotency guard).
   */
  private bool $nameSet = false;

  /**
   * Whether `setParent` has already been called (idempotency guard).
   */
  private bool $parentSet = false;

  /**
   * Construct a new State.
   *
   * @param null|string $state Raw state name. Pass `null` for the
   *                           no-state sentinel or `'*'` for any-state.
   * @param null|string $groupName Explicit group-name prefix. When omitted
   *                               the bootstrap-assigned parent group's
   *                               `fullGroupName()` is used.
   */
  public function __construct(
    ?string $state = null,
    ?string $groupName = null,
  ) {
    $this->rawState = $state;
    $this->groupName = $groupName;
  }

  // ------------------------------------------------------------------ //
  // Framework-internal bootstrap setters (called once by StatesGroup::bootstrap)
  // ------------------------------------------------------------------ //

  /**
   * Default the raw state to `$name` when no explicit state was given at
   * construction.  Idempotent — subsequent calls are silently ignored.
   *
   * Framework-internal: called once by `StatesGroup::bootstrap()` to mirror
   * Python's `__set_name__` PEP 487 hook.
   */
  public function setName(string $name): void
  {
    if ($this->nameSet) {
      return;
    }

    if ($this->rawState === null) {
      $this->rawState = $name;
    }

    $this->nameSet = true;
  }

  /**
   * Bind this state to the owning `StatesGroup` subclass.  Idempotent —
   * subsequent calls are silently ignored.
   *
   * Framework-internal: called once by `StatesGroup::bootstrap()` to mirror
   * Python's `set_parent(group)`.
   *
   * @param class-string<StatesGroup> $groupClass
   */
  public function setParent(string $groupClass): void
  {
    if ($this->parentSet) {
      return;
    }

    $this->group = $groupClass;
    $this->parentSet = true;
  }

  // ------------------------------------------------------------------ //
  // Public accessors
  // ------------------------------------------------------------------ //

  /**
   * Return the fully-qualified state name, or `null` / `'*'` for sentinels.
   *
   * Resolution order for the group prefix:
   * 1. Explicit `$groupName` constructor arg.
   * 2. `$group::fullGroupName()` (set at bootstrap).
   * 3. The bare fallback `'@'`.
   *
   * Mirrors `State.state` property (`aiogram/fsm/state.py:36-46`).
   */
  public function state(): ?string
  {
    if ($this->rawState === null || $this->rawState === '*') {
      return $this->rawState;
    }

    if ($this->groupName !== null) {
      return "{$this->groupName}:{$this->rawState}";
    }

    if ($this->group !== null) {
      $groupName = ($this->group)::fullGroupName();

      return "{$groupName}:{$this->rawState}";
    }

    return "@:{$this->rawState}";
  }

  /**
   * Return the raw (unqualified) state name as stored.
   */
  public function rawState(): ?string
  {
    return $this->rawState;
  }

  // ------------------------------------------------------------------ //
  // Comparison / hashing
  // ------------------------------------------------------------------ //

  /**
   * Equality comparison.
   *
   * Accepts another `State` (compare qualified names) or a `string`
   * (compare the qualified name to the string).
   *
   * Mirrors `State.__eq__` (`aiogram/fsm/state.py:70-76`).
   */
  public function equals(State|string $other): bool
  {
    if ($other instanceof self) {
      return $this->state() === $other->state();
    }

    return $this->state() === $other;
  }

  // ------------------------------------------------------------------ //
  // Filter / callable contract
  // ------------------------------------------------------------------ //

  /**
   * Evaluate whether the event is in this state.
   *
   * Reads `$kwargs['rawState'] ?? null` from the variadic dispatcher
   * kwargs bag and matches:
   * - always returns `true` when `$this->rawState === '*'`.
   * - returns `true` when the raw-state string equals `$this->state()`.
   * - returns `false` otherwise.
   *
   * Signature matches `Filter::__invoke` so instances can be registered
   * directly as dispatcher filters.
   *
   * Mirrors `State.__call__` (`aiogram/fsm/state.py:60-68`).
   *
   * @return array<string, mixed>|bool
   */
  public function __invoke(object $event, mixed ...$kwargs): array|bool
  {
    if ($this->rawState === '*') {
      return true;
    }

    $rawState = $kwargs['rawState'] ?? null;

    return $rawState === $this->state();
  }
}
