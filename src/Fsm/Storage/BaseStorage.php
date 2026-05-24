<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Storage;

use Gruven\PhpBotGram\Fsm\State;

/**
 * Abstract base for all FSM storage backends.
 *
 * Mirrors `aiogram.fsm.storage.base.BaseStorage` (`aiogram/fsm/storage/base.py:103-200`).
 * Five methods are abstract; two concrete default implementations are provided
 * exactly as in the Python original.
 *
 * Async note: phpbotgram exposes a sync-style public surface — methods return
 * concrete values, not `Future<>` / `Promise<>`. Implementations may suspend
 * internally via Amp (Revolt event loop) but must never leak async primitives
 * through this interface.
 */
abstract class BaseStorage
{
  /**
   * Persist the FSM state for the given key.
   *
   * @param StorageKey $key Storage address.
   * @param null|State|string $state New state value.
   *                                 `string` — a raw state name already serialised.
   *                                 `State`  — a `State` instance; implementations call
   *                                 `$state->state()` to obtain the qualified name.
   *                                 `null`   — clears the state.
   *
   * Mirrors `BaseStorage.set_state` (`base.py:127-130`).
   */
  abstract public function setState(StorageKey $key, State|string|null $state = null): void;

  /**
   * Retrieve the FSM state for the given key.
   *
   * @param StorageKey $key Storage address.
   *
   * @return null|string Serialised state name, or `null` if no state is stored.
   *
   * Mirrors `BaseStorage.get_state` (`base.py:132-135`).
   */
  abstract public function getState(StorageKey $key): ?string;

  /**
   * Persist the FSM data payload for the given key.
   *
   * @param StorageKey $key Storage address.
   * @param array<string, mixed> $data Data map to store (replaces the current record entirely).
   *
   * Mirrors `BaseStorage.set_data` (`base.py:137-141`).
   * Python `Mapping[str, Any]` → PHP `array<string, mixed>`.
   */
  abstract public function setData(StorageKey $key, array $data): void;

  /**
   * Retrieve the FSM data payload for the given key.
   *
   * @param StorageKey $key Storage address.
   *
   * @return array<string, mixed> Current data map (may be empty).
   *
   * Mirrors `BaseStorage.get_data` (`base.py:143-146`).
   */
  abstract public function getData(StorageKey $key): array;

  /**
   * Release all resources held by this storage instance.
   *
   * Mirrors `BaseStorage.close` (`base.py:148-149`).
   */
  abstract public function close(): void;

  /**
   * Read a single key from the data map stored at `$storageKey`.
   *
   * Returns `$default` when the dict key is absent. PHP collapses the
   * upstream `@overload` pattern into one method with a `mixed $default = null`
   * parameter.
   *
   * @param StorageKey $storageKey Storage address.
   * @param string $dictKey Key within the data map.
   * @param mixed $default Value to return when `$dictKey` is not present.
   *
   * @return mixed The stored value, or `$default`.
   *
   * Mirrors `BaseStorage.get_value` (`base.py:151-168`).
   */
  public function getValue(StorageKey $storageKey, string $dictKey, mixed $default = null): mixed
  {
    $data = $this->getData($storageKey);

    return array_key_exists($dictKey, $data) ? $data[$dictKey] : $default;
  }

  /**
   * Merge `$data` into the existing record at `$key` and persist the result.
   *
   * Loads the current data map, merges the supplied patch on top with
   * `array_merge` (later keys win on collision), persists the merged map,
   * and returns a copy so that mutations to the returned array cannot bleed
   * back into storage.
   *
   * @param StorageKey $key Storage address.
   * @param array<string, mixed> $data Partial data map to merge in.
   *
   * @return array<string, mixed> A copy of the merged data map as it was persisted.
   *
   * Mirrors `BaseStorage.update_data` (`base.py:170-181`).
   * Upstream returns `current_data.copy()` after in-place update.
   */
  public function updateData(StorageKey $key, array $data): array
  {
    $current = $this->getData($key);
    $merged = array_merge($current, $data);
    $this->setData($key, $merged);

    // Return a copy so callers cannot mutate storage indirectly.
    return $merged;
  }
}
