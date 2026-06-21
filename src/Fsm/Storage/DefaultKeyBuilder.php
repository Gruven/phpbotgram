<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Storage;

/**
 * Simple colon-joined key builder with an `fsm` prefix.
 *
 * Mirrors `aiogram.fsm.storage.base.DefaultKeyBuilder`
 * (`aiogram/fsm/storage/base.py:42-99`). Segment order matches upstream
 * exactly:
 *
 *   `<prefix>[:<botId>][:<businessConnectionId>]:<chatId>[:<threadId>]:<userId>[:<destiny>][:<part>]`
 *
 * Optional segments are controlled by constructor flags:
 *   - `$withBotId`                 — include `botId`
 *   - `$withBusinessConnectionId`  — include `businessConnectionId` (only when non-null on the key)
 *   - `$withDestiny`               — always include the `destiny` segment.
 *
 * When `$withDestiny` is `false` (the default), the default destiny segment is
 * omitted for compact keys, but non-default destiny values are still included.
 * This keeps ordinary FSM keys stable while allowing scene-history and custom
 * destiny slots to live in isolated backend keys.
 */
final class DefaultKeyBuilder implements KeyBuilder
{
  /**
   * @param string $prefix String prefix prepended to every generated key.
   * @param string $separator Token placed between each key segment.
   * @param bool $withBotId When `true`, the bot ID segment is included.
   * @param bool $withBusinessConnectionId When `true`, the business-connection-ID segment is
   *                                       included (only if the key's `businessConnectionId` is non-null).
   * @param bool $withDestiny When `true`, the destiny segment is always included.
   *                          When `false`, only non-default destiny values are included.
   */
  public function __construct(
    private readonly string $prefix = 'fsm',
    private readonly string $separator = ':',
    private readonly bool $withBotId = false,
    private readonly bool $withBusinessConnectionId = false,
    private readonly bool $withDestiny = false,
  ) {}

  /**
   * Assemble and return the storage key string.
   *
   * @param StorageKey $key Contextual key.
   * @param null|StoragePart $part Optional sub-record discriminator; appended as the final segment when non-null.
   *
   * @return string Assembled key string.
   */
  public function build(StorageKey $key, ?StoragePart $part = null): string
  {
    $parts = [$this->prefix];

    if ($this->withBotId) {
      $parts[] = (string)$key->botId;
    }

    if ($this->withBusinessConnectionId && $key->businessConnectionId !== null) {
      $parts[] = $key->businessConnectionId;
    }

    $parts[] = (string)$key->chatId;

    if ($key->threadId !== null) {
      $parts[] = (string)$key->threadId;
    }

    $parts[] = (string)$key->userId;

    if ($this->withDestiny || $key->destiny !== StorageKey::DEFAULT_DESTINY) {
      $parts[] = $key->destiny;
    }

    if ($part !== null) {
      $parts[] = $part->value;
    }

    return implode($this->separator, $parts);
  }
}
