<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Storage;

/**
 * Represents the three sub-record kinds that a key builder can address within
 * a single FSM storage entry.
 *
 * Mirrors Python's `Literal["data", "state", "lock"]` type alias used in
 * `KeyBuilder.build()` / `DefaultKeyBuilder.build()` at
 * `aiogram/fsm/storage/base.py:31` and `base.py:78`. Converting the literal
 * union to a backed enum gives type-safe call sites in PHP and lets the
 * compiler enforce exhaustiveness without extra `match` branches.
 *
 * Wire values are intentionally kept identical to the upstream literals so
 * that key strings produced by `DefaultKeyBuilder` are byte-compatible with
 * the Python implementation's output.
 */
enum StoragePart: string
{
  case Data = 'data';
  case State = 'state';
  case Lock = 'lock';
}
