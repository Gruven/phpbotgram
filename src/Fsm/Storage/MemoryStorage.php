<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Storage;

use Gruven\PhpBotGram\Fsm\State;

/**
 * In-memory FSM storage backend.
 *
 * Mirrors `aiogram.fsm.storage.memory.MemoryStorage`
 * (`aiogram/fsm/storage/memory.py:25-72`). All state and data are held in a
 * plain PHP array keyed by a derived string; the array is reset on `close()`.
 *
 * WARNING: All data is lost when the PHP process exits. Do not use in
 * production if state persistence across restarts is required.
 *
 * Key derivation: identical to `SimpleEventIsolation::buildKey()` —
 * `<botId>:<chatId>:<threadId|"">:<userId>:<businessConnectionId|"">:<destiny>`.
 *
 * Python `defaultdict` equivalent: `record()` auto-vivifies a fresh
 * `MemoryStorageRecord` on first access for any key.
 */
final class MemoryStorage extends BaseStorage
{
  /**
   * In-memory record store. Keyed by the derived string returned from `keyOf`.
   *
   * @var array<string, MemoryStorageRecord>
   */
  private array $storage = [];

  /**
   * Persist the FSM state for the given key.
   *
   * When `$state` is a `State` instance, `$state->state()` is called to
   * obtain the fully-qualified state string. When it is a plain string or
   * `null`, the value is stored as-is.
   *
   * Mirrors `MemoryStorage.set_state` (`memory.py:44-45`):
   * ```python
   * self.storage[key].state = state.state if isinstance(state, State) else state
   * ```
   */
  public function setState(StorageKey $key, State|string|null $state = null): void
  {
    if ($state instanceof State) {
      $this->record($key)->state = $state->state();

      return;
    }

    $this->record($key)->state = $state;
  }

  /**
   * Retrieve the FSM state for the given key.
   *
   * Mirrors `MemoryStorage.get_state` (`memory.py:47-48`).
   */
  public function getState(StorageKey $key): ?string
  {
    return $this->record($key)->state;
  }

  /**
   * Persist the FSM data payload for the given key (replaces the record entirely).
   *
   * PHP arrays are value-typed, so `$data` is implicitly copied on assignment —
   * no explicit `$data->copy()` is needed, matching upstream `memory.py:54`:
   * ```python
   * self.storage[key].data = data.copy()
   * ```
   *
   * Mirrors `MemoryStorage.set_data` (`memory.py:50-54`).
   */
  public function setData(StorageKey $key, array $data): void
  {
    $this->record($key)->data = $data;
  }

  /**
   * Retrieve a COPY of the FSM data payload for the given key.
   *
   * PHP arrays are value-typed, so returning `$record->data` already yields an
   * independent copy — mutations to the returned array do not bleed back into
   * storage. This matches upstream `memory.py:57`:
   * ```python
   * return self.storage[key].data.copy()
   * ```
   *
   * Mirrors `MemoryStorage.get_data` (`memory.py:56-57`).
   */
  public function getData(StorageKey $key): array
  {
    return $this->record($key)->data;
  }

  /**
   * Release all storage records.
   *
   * Mirrors `MemoryStorage.close` (`memory.py:41-42`). The upstream
   * implementation is a no-op coroutine; here we reset the internal array so
   * that memory is freed promptly.
   */
  public function close(): void
  {
    $this->storage = [];
  }

  // ------------------------------------------------------------------ //
  // Internal helpers
  // ------------------------------------------------------------------ //

  /**
   * Auto-vivify and return the `MemoryStorageRecord` for `$key`.
   *
   * Equivalent to Python `defaultdict(MemoryStorageRecord)[key]`.
   */
  private function record(StorageKey $key): MemoryStorageRecord
  {
    $k = $this->keyOf($key);

    if (!isset($this->storage[$k])) {
      $this->storage[$k] = new MemoryStorageRecord();
    }

    return $this->storage[$k];
  }

  /**
   * Derive a stable string key from all `StorageKey` fields.
   *
   * Format: `<botId>:<chatId>:<threadId|"">:<userId>:<businessConnectionId|"">:<destiny>`
   *
   * Mirrors the key derivation used by `SimpleEventIsolation::buildKey()`.
   */
  private function keyOf(StorageKey $key): string
  {
    return implode(':', [
      $key->botId,
      $key->chatId,
      $key->threadId ?? '',
      $key->userId,
      $key->businessConnectionId ?? '',
      $key->destiny,
    ]);
  }
}
