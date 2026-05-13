<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Hybrid dict / object that lets `MagicFilter` resolve both `F->foo` (the
 * attribute path) and `F[foo]` (the subscript path) against a single value.
 *
 * Port of upstream `magic_filter.attrdict.AttrDict`
 * (`magic_filter/attrdict.py`). Used by the `MagicData` filter to wrap the
 * dispatcher's middleware-data dict so a `MagicFilter` rule can transparently
 * walk either `F->event_chat` or `F['event_chat']`.
 *
 * @implements ArrayAccess<array-key, mixed>
 * @implements IteratorAggregate<array-key, mixed>
 */
final class AttrDict implements ArrayAccess, Countable, IteratorAggregate
{
  /** @var array<array-key, mixed> */
  private array $data;

  /** @param array<array-key, mixed> $data */
  public function __construct(array $data = [])
  {
    $this->data = $data;
  }

  public function __get(string $name): mixed
  {
    return $this->data[$name] ?? null;
  }

  public function __set(string $name, mixed $value): void
  {
    $this->data[$name] = $value;
  }

  public function __isset(string $name): bool
  {
    return isset($this->data[$name]);
  }

  public function __unset(string $name): void
  {
    unset($this->data[$name]);
  }

  public function offsetExists(mixed $offset): bool
  {
    return array_key_exists($offset, $this->data);
  }

  public function offsetGet(mixed $offset): mixed
  {
    return $this->data[$offset] ?? null;
  }

  public function offsetSet(mixed $offset, mixed $value): void
  {
    if ($offset === null) {
      $this->data[] = $value;

      return;
    }
    $this->data[$offset] = $value;
  }

  public function offsetUnset(mixed $offset): void
  {
    unset($this->data[$offset]);
  }

  public function count(): int
  {
    return count($this->data);
  }

  public function getIterator(): Traversable
  {
    return new ArrayIterator($this->data);
  }

  /** @return array<array-key, mixed> */
  public function toArray(): array
  {
    return $this->data;
  }
}
