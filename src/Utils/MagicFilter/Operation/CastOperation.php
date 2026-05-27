<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter\Operation;

use Closure;
use Gruven\PhpBotGram\Utils\MagicFilter\Exception\RejectOperations;
use Throwable;

/**
 * Apply a unary transformation to the running value: `F->id->cast(intval(...))`
 * passes the value through `intval` and forwards the result.
 *
 * Mirrors upstream `magic_filter.operations.cast.CastOperation`
 * (`magic_filter/operations/cast.py`). Any exception from the cast
 * function is caught and re-raised as `RejectOperations` so the chain
 * treats a failed cast like a missing attribute (the rest of the chain
 * is short-circuited unless an `important` operation rescues it).
 *
 * The constructor parameter is a `Closure`; non-Closure callables passed
 * via `MagicFilter::cast()` are wrapped via `Closure::fromCallable(...)`
 * by the caller so the operation always holds a Closure.
 */
final class CastOperation extends BaseOperation
{
  public function __construct(public readonly Closure $func) {}

  public function resolve(mixed $value, mixed $initialValue): mixed
  {
    try {
      return ($this->func)($value);
    } catch (Throwable $e) {
      throw new RejectOperations($e);
    }
  }
}
