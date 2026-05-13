<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter\Operation;

use ArrayAccess;
use Error;
use Gruven\PhpBotGram\Utils\MagicFilter\Exception\RejectOperations;

/**
 * Read a named attribute from the running value: `F->message->text`
 * resolves to `$value->text` (object) or `$value['text']` (array/ArrayAccess).
 *
 * Mirrors upstream `magic_filter.operations.getattr.GetAttributeOperation`
 * (`magic_filter/operations/getattr.py`). Python's single `getattr()` covers
 * both attribute and item-like lookups via `__getattr__`; PHP splits them
 * but `MagicFilter::__get` always dispatches here, so we honour the same
 * fallback for arrays / `ArrayAccess` to keep `AttrDict`-style call sites
 * working.
 *
 * On lookup failure we raise `RejectOperations` so the resolver short-
 * circuits the rest of the chain (and an upstream `__invert__` / OR can
 * still rescue the verdict via the `important` flag).
 */
final class GetAttributeOperation extends BaseOperation
{
  public function __construct(public readonly string $name) {}

  public function resolve(mixed $value, mixed $initialValue): mixed
  {
    if (is_object($value)) {
      // `isset` returns false for declared-null properties, which would
      // mis-classify a legitimate `null` field as "missing". `property_exists`
      // is the correct predicate for declared-but-null properties.
      if (property_exists($value, $this->name) || isset($value->{$this->name})) {
        try {
          return $value->{$this->name};
        } catch (Error $e) {
          // Unset readonly / typed properties throw on access; bubble as a
          // chain rejection so the resolver treats them like missing fields.
          throw new RejectOperations($e);
        }
      }

      // `AttrDict`-style hybrids resolve attribute lookups through their
      // `ArrayAccess` interface. Honour that path before giving up on the
      // attribute. We probe both `offsetExists` and `__get`-style access
      // so a plain `__get` override (no `ArrayAccess`) still works.
      if ($value instanceof ArrayAccess && $value->offsetExists($this->name)) {
        return $value->offsetGet($this->name);
      }

      // Last-ditch: an object that overrides `__get` may answer for names
      // not literally declared. Try and trap any error/exception path.
      if (method_exists($value, '__get')) {
        try {
          return $value->{$this->name};
        } catch (Error $e) {
          throw new RejectOperations($e);
        }
      }

      throw new RejectOperations(
        new Error('Object of type ' . $value::class . " has no attribute '{$this->name}'."),
      );
    }

    if (is_array($value) && array_key_exists($this->name, $value)) {
      return $value[$this->name];
    }

    throw new RejectOperations(
      new Error('Value of type ' . get_debug_type($value) . " has no attribute '{$this->name}'."),
    );
  }
}
