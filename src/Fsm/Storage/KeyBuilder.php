<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Storage;

/**
 * Contract for assembling a string key from an FSM {@see StorageKey} and an
 * optional sub-record discriminator.
 *
 * Mirrors the abstract base class `aiogram.fsm.storage.base.KeyBuilder`
 * (`aiogram/fsm/storage/base.py:24-39`). In Python the contract is expressed
 * as an `ABC` with an `@abstractmethod`; in PHP an `interface` carries the
 * same semantics with less boilerplate.
 *
 * Implementors receive a fully-constructed {@see StorageKey} and an optional
 * {@see StoragePart} discriminator, and must return the opaque string that the
 * storage backend will use as the record key (e.g. a Redis key or a MongoDB
 * document `_id`).
 */
interface KeyBuilder
{
  /**
   * Build the storage key string for the given FSM context.
   *
   * @param StorageKey $key Contextual key carrying bot/chat/user/destiny coordinates.
   * @param null|StoragePart $part Sub-record discriminator (`data`, `state`, `lock`), or `null`
   *                               to address the entry without a part suffix.
   *
   * @return string Opaque key string suitable for use as a storage backend record address.
   */
  public function build(StorageKey $key, ?StoragePart $part = null): string;
}
