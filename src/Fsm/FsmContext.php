<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm;

use Gruven\PhpBotGram\Fsm\Storage\BaseStorage;
use Gruven\PhpBotGram\Fsm\Storage\StorageKey;

/**
 * Per-context FSM handle that delegates all operations to the backing storage.
 *
 * Mirrors `aiogram.fsm.context.FSMContext` (`aiogram/fsm/context.py`).
 *
 * Each instance is bound to a specific `StorageKey` (bot + chat + user +
 * optional thread/destiny) so callers can read/write FSM state and data
 * without managing the key themselves.
 *
 * Async note: phpbotgram exposes a sync-style public surface. Implementations
 * of `BaseStorage` may suspend internally via Amp (Revolt event loop) but must
 * not leak async primitives through this interface.
 *
 * State type note: upstream Python uses `str | State | None`. PHP tightens
 * `object` to `State` once `Gruven\PhpBotGram\Fsm\State` is available
 * (Task 5.5 has landed). The current signature uses `string|State|null`
 * which maps cleanly to `BaseStorage::setState`.
 */
final class FsmContext
{
  /**
   * @param BaseStorage $storage Backing storage implementation.
   * @param StorageKey $key Contextual address for all operations.
   */
  public function __construct(
    public readonly BaseStorage $storage,
    public readonly StorageKey $key,
  ) {}

  // ------------------------------------------------------------------ //
  // State operations
  // ------------------------------------------------------------------ //

  /**
   * Set the FSM state for this context.
   *
   * When `$state` is a `State` instance, its qualified name (from `state()`)
   * is extracted before being forwarded to the storage backend. This ensures
   * that `BaseStorage::setState` always receives a `string|null` regardless
   * of whether the caller passed a `State` object or a raw string.
   *
   * Delegates to `BaseStorage::setState($this->key, ...)`.
   *
   * @param null|State|string $state New state. `null` clears the state.
   *
   * Mirrors `FSMContext.set_state` (`aiogram/fsm/context.py`).
   */
  public function setState(null|State|string $state = null): void
  {
    $this->storage->setState($this->key, $state instanceof State ? $state->state() : $state);
  }

  /**
   * Get the current FSM state for this context.
   *
   * Delegates to `BaseStorage::getState($this->key)`.
   *
   * @return ?string Serialised state name, or `null` if no state is set.
   *
   * Mirrors `FSMContext.get_state` (`aiogram/fsm/context.py`).
   */
  public function getState(): ?string
  {
    return $this->storage->getState($this->key);
  }

  // ------------------------------------------------------------------ //
  // Data operations
  // ------------------------------------------------------------------ //

  /**
   * Replace the FSM data payload for this context entirely.
   *
   * Delegates to `BaseStorage::setData($this->key, $data)`.
   *
   * @param array<string, mixed> $data Data map to store.
   *
   * Mirrors `FSMContext.set_data` (`aiogram/fsm/context.py`).
   */
  public function setData(array $data): void
  {
    $this->storage->setData($this->key, $data);
  }

  /**
   * Retrieve the current FSM data payload for this context.
   *
   * Delegates to `BaseStorage::getData($this->key)`.
   *
   * @return array<string, mixed> Current data map (may be empty).
   *
   * Mirrors `FSMContext.get_data` (`aiogram/fsm/context.py`).
   */
  public function getData(): array
  {
    return $this->storage->getData($this->key);
  }

  /**
   * Read a single value from the FSM data payload for this context.
   *
   * Delegates to `BaseStorage::getValue($this->key, $key, $default)`.
   *
   * @param string $key Dict key within the data payload.
   * @param mixed $default Value returned when `$key` is absent.
   *
   * @return mixed The stored value, or `$default`.
   *
   * Mirrors `FSMContext.get_value` (`aiogram/fsm/context.py`).
   */
  public function getValue(string $key, mixed $default = null): mixed
  {
    return $this->storage->getValue($this->key, $key, $default);
  }

  /**
   * Merge data into the existing FSM data payload for this context.
   *
   * Upstream merge semantics (`aiogram/fsm/context.py`):
   * ```python
   * kwargs.update(data or {})   # data wins over kwargs on overlap
   * return await self.storage.update_data(key=self.key, data=kwargs)
   * ```
   * PHP equivalent: `array_merge($kwargs, $data ?? [])` so `$data` entries
   * overwrite same-keyed `$kwargs` entries.
   *
   * Delegates to `BaseStorage::updateData($this->key, $merged)`.
   *
   * @param ?array<string, mixed> $data Optional explicit data to merge.
   * @param mixed ...$kwargs Additional named key/value pairs to include.
   *
   * @return array<string, mixed> The merged data map as persisted.
   *
   * Mirrors `FSMContext.update_data` (`aiogram/fsm/context.py`).
   */
  public function updateData(?array $data = null, mixed ...$kwargs): array
  {
    // Start from kwargs, then overlay $data so that $data wins on overlap.
    /** @var array<string, mixed> $kwargs */
    $merged = array_merge($kwargs, $data ?? []);

    return $this->storage->updateData($this->key, $merged);
  }

  // ------------------------------------------------------------------ //
  // Clear
  // ------------------------------------------------------------------ //

  /**
   * Reset both state and data to their empty defaults.
   *
   * Equivalent to calling `setState(null)` followed by `setData([])`.
   *
   * Mirrors `FSMContext.clear` (`aiogram/fsm/context.py`).
   */
  public function clear(): void
  {
    $this->setState(null);
    $this->setData([]);
  }
}
