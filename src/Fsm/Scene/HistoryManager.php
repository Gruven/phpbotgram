<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Scene;

use Gruven\PhpBotGram\Fsm\FsmContext;
use Gruven\PhpBotGram\Fsm\Storage\MemoryStorageRecord;

/**
 * Manages the scene history stack for back-navigation.
 *
 * Stores a bounded list of `{state, data}` snapshots in a separate FSM destiny
 * slot so that they do not interfere with the main conversation state.  Scene
 * lifecycle methods (`leave`, `back`, `exit`) interact with this class via the
 * `HistoryManagerInterface` surface exposed through `SceneWizard`.
 *
 * Mirrors `HistoryManager` (`aiogram/fsm/scene.py:32-105`).
 *
 * ## Destiny isolation
 *
 * The history records are written to a cloned `StorageKey` whose `destiny` tag
 * is set to `$destiny` (default `'scenes_history'`). All other key fields
 * (bot, chat, user, thread, business connection) remain identical, so the
 * history stack is per-conversation but separate from the primary FSM slot.
 *
 * ## Size cap
 *
 * After every `push()` the list is trimmed to the last `$size` entries
 * (oldest entries are evicted). Python equivalent: `history[-self._size:]`.
 */
final class HistoryManager implements HistoryManagerInterface
{
  /**
   * FSM context that addresses the history-specific storage slot.
   */
  private readonly FsmContext $historyState;

  /**
   * @param FsmContext $state Main FSM context (provides storage + key).
   * @param string $destiny Destiny tag for the history storage slot.
   * @param int $size Maximum number of history entries to retain.
   */
  public function __construct(
    private readonly FsmContext $state,
    string $destiny = 'scenes_history',
    private readonly int $size = 10,
  ) {
    $this->historyState = new FsmContext(
      storage: $state->storage,
      key: $state->key->withDestiny($destiny),
    );
  }

  // ------------------------------------------------------------------ //
  // HistoryManagerInterface
  // ------------------------------------------------------------------ //

  /**
   * Remove all entries from the history stack.
   *
   * Mirrors `HistoryManager.clear` (`aiogram/fsm/scene.py:79-80`).
   */
  public function clear(): void
  {
    $this->historyState->setData([]);
  }

  /**
   * Record the current main FSM state and data onto the top of the stack.
   *
   * Mirrors `HistoryManager.snapshot` (`aiogram/fsm/scene.py:82-85`).
   */
  public function snapshot(): void
  {
    $state = $this->state->getState();
    $data = $this->state->getData();
    $this->push($state, $data);
  }

  /**
   * Pop the last history entry and restore the main FSM to its values.
   *
   * When the stack is empty after popping (or was already empty), the main
   * FSM state is cleared to `null` with an empty data map.
   *
   * Mirrors `HistoryManager.rollback` (`aiogram/fsm/scene.py:87-96`).
   *
   * @return null|string The state string from the popped entry, or `null`
   *                     when the history was empty.
   */
  public function rollback(): ?string
  {
    $previous = $this->pop();

    if ($previous === null) {
      $this->setState(null, []);

      return null;
    }

    $this->setState($previous->state, $previous->data);

    return $previous->state;
  }

  // ------------------------------------------------------------------ //
  // Extended public surface (mirrors upstream HistoryManager)
  // ------------------------------------------------------------------ //

  /**
   * Append a `{state, data}` entry to the history stack.
   *
   * If the resulting list length exceeds `$this->size`, the oldest entries are
   * dropped so that only the most recent `$size` entries are retained.
   *
   * Mirrors `HistoryManager.push` (`aiogram/fsm/scene.py:56-64`).
   *
   * @param null|string $state Serialised state name (or `null`).
   * @param array<string, mixed> $data FSM data payload.
   */
  public function push(?string $state, array $data): void
  {
    $history = $this->loadHistory();
    $history[] = ['state' => $state, 'data' => $data];

    if (count($history) > $this->size) {
      $history = array_slice($history, -$this->size);
    }

    $this->historyState->updateData(['history' => $history]);
  }

  /**
   * Remove and return the last history entry.
   *
   * After the entry is removed, if the stack is now empty the history data is
   * cleared (set to `{}`). Otherwise the trimmed list is persisted.
   *
   * Mirrors `HistoryManager.pop` (`aiogram/fsm/scene.py:66-76`).
   *
   * @return null|MemoryStorageRecord The popped entry as a record, or `null`
   *                                  when the history stack was empty.
   */
  public function pop(): ?MemoryStorageRecord
  {
    $history = $this->loadHistory();

    if ($history === []) {
      return null;
    }

    /** @var array{state: null|string, data: array<string, mixed>} $entry */
    $entry = array_pop($history);

    if ($history === []) {
      $this->historyState->setData([]);
    } else {
      $this->historyState->updateData(['history' => $history]);
    }

    return new MemoryStorageRecord(
      data: $entry['data'],
      state: $entry['state'],
    );
  }

  /**
   * Peek at the last history entry without removing it.
   *
   * Mirrors `HistoryManager.get` (`aiogram/fsm/scene.py:…`).
   *
   * @return null|MemoryStorageRecord The last entry as a record, or `null`
   *                                  when the history stack is empty.
   */
  public function get(): ?MemoryStorageRecord
  {
    $history = $this->loadHistory();

    if ($history === []) {
      return null;
    }

    /** @var array{state: null|string, data: array<string, mixed>} $entry */
    $entry = end($history);

    return new MemoryStorageRecord(
      data: $entry['data'],
      state: $entry['state'],
    );
  }

  /**
   * Return all history entries as a list of `MemoryStorageRecord` objects.
   *
   * Mirrors `HistoryManager.all` (`aiogram/fsm/scene.py:…`).
   *
   * @return list<MemoryStorageRecord>
   */
  public function all(): array
  {
    $history = $this->loadHistory();

    return array_map(
      /** @param array{state: null|string, data: array<string, mixed>} $entry */
      static fn(array $entry): MemoryStorageRecord => new MemoryStorageRecord(
        data: $entry['data'],
        state: $entry['state'],
      ),
      $history,
    );
  }

  // ------------------------------------------------------------------ //
  // Private helpers
  // ------------------------------------------------------------------ //

  /**
   * Load the raw history list from the history storage slot.
   *
   * @return list<array{state: null|string, data: array<string, mixed>}>
   */
  private function loadHistory(): array
  {
    $payload = $this->historyState->getData();

    /** @var list<array{state: null|string, data: array<string, mixed>}> */
    return $payload['history'] ?? [];
  }

  /**
   * Apply `$state` and `$data` to the main FSM context.
   *
   * @param array<string, mixed> $data
   */
  private function setState(?string $state, array $data): void
  {
    $this->state->setState($state);
    $this->state->setData($data);
  }
}
