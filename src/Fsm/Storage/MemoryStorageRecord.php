<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Storage;

/**
 * Mutable value object holding a single FSM storage slot.
 *
 * Mirrors the `MemoryStorageRecord` dataclass from
 * `aiogram/fsm/storage/memory.py:20-22`:
 *
 * ```python
 *
 * @dataclass
 * class MemoryStorageRecord:
 *     data: dict[str, Any] = field(default_factory=dict)
 *     state: str | None = None
 * ```
 *
 * PHP arrays are value-typed, so assigning `$record->data = $map` stores a
 * copy of `$map` — no additional deep-copy helper is required for scalar /
 * array values. Objects nested inside `data` are still referenced (shallow),
 * matching Python's `dict.copy()` semantics used by upstream `MemoryStorage`.
 */
final class MemoryStorageRecord
{
  /**
   * @param array<string, mixed> $data Arbitrary key-value payload for the FSM record.
   * @param null|string $state Serialised state name, or `null` when no state is active.
   */
  public function __construct(
    public array $data = [],
    public ?string $state = null,
  ) {}
}
