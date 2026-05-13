<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter\Operation;

use ArrayAccess;
use Error;
use Gruven\PhpBotGram\Utils\MagicFilter\Exception\RejectOperations;
use Gruven\PhpBotGram\Utils\MagicFilter\Exception\SwitchModeToAll;
use Gruven\PhpBotGram\Utils\MagicFilter\Exception\SwitchModeToAny;
use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilter;

/**
 * Subscript / index access: `F->items[$key]` resolves to `$value[$key]`.
 *
 * Mirrors upstream `magic_filter.operations.getitem.GetItemOperation`
 * (`magic_filter/operations/getitem.py`). Two special keys flip the
 * resolver into a "fan-out" mode over an iterable subject:
 *
 * - `MagicFilter::WILDCARD_ALL` (PHP equivalent of Python's `[:]` empty
 *   slice) — raise `SwitchModeToAll`, resolver re-runs the rest of the
 *   chain on every element and accepts when ALL succeed.
 * - `MagicFilter::WILDCARD_ANY` (PHP equivalent of Python's `[...]`
 *   Ellipsis) — raise `SwitchModeToAny`, resolver accepts when ANY
 *   element succeeds.
 *
 * Both wildcards require the current value to be iterable; otherwise we
 * fall through to the literal subscript path and let it reject normally.
 */
final class GetItemOperation extends BaseOperation
{
  public function __construct(public readonly mixed $key) {}

  public function resolve(mixed $value, mixed $initialValue): mixed
  {
    if (is_iterable($value)) {
      // Wildcard branches mirror upstream's `slice(None, None, None)` and
      // `...` (Ellipsis) sentinels. We don't have either in PHP; we expose
      // string sentinels on MagicFilter (cf. `MagicFilter::all`/`any`).
      if ($this->key === MagicFilter::WILDCARD_ANY) {
        throw new SwitchModeToAny();
      }

      if ($this->key === MagicFilter::WILDCARD_ALL) {
        throw new SwitchModeToAll($this->key);
      }
    }

    if (is_array($value)) {
      if (!is_int($this->key) && !is_string($this->key)) {
        throw new RejectOperations(new Error('Array key must be int or string.'));
      }

      if (!array_key_exists($this->key, $value)) {
        throw new RejectOperations(new Error("Array key '{$this->keyDescription()}' not found."));
      }

      return $value[$this->key];
    }

    if ($value instanceof ArrayAccess) {
      if (!$value->offsetExists($this->key)) {
        throw new RejectOperations(new Error("ArrayAccess key '{$this->keyDescription()}' not found."));
      }

      return $value->offsetGet($this->key);
    }

    if (is_string($value) && is_int($this->key)) {
      // String subscripting is a common Pythonic pattern — `value[0]`.
      if ($this->key < -strlen($value) || $this->key >= strlen($value)) {
        throw new RejectOperations(new Error('String index out of range.'));
      }

      return $value[$this->key];
    }

    throw new RejectOperations(
      new Error('Cannot subscript value of type ' . get_debug_type($value) . '.'),
    );
  }

  private function keyDescription(): string
  {
    if (is_scalar($this->key)) {
      return (string)$this->key;
    }

    return get_debug_type($this->key);
  }
}
