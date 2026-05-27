<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter\Operation;

use Error;
use Gruven\PhpBotGram\Utils\MagicFilter\Exception\RejectOperations;

/**
 * Invoke a named method on the running value: `F->text->lower()` resolves
 * to `$value->lower()`. The Python upstream models this as `__getattr__`
 * (binding the method) followed by `__call__` (invoking it); PHP doesn't
 * expose bound methods as first-class values, so we collapse the pair
 * into one step here.
 *
 * Used by `MagicFilter::__call`: unknown method names that aren't named-
 * operation builders (`equals`, `func`, …) are wrapped in this op.
 *
 * Reject behaviour: missing method → `RejectOperations` so the resolver
 * blanks the running value to `null` (matching upstream's
 * `getattr` → `AttributeError` → `RejectOperations` chain).
 */
final class MethodCallOperation extends BaseOperation
{
  /**
   * @param list<mixed> $args
   * @param array<string, mixed> $kwargs
   */
  public function __construct(
    public readonly string $name,
    public readonly array $args,
    public readonly array $kwargs,
  ) {}

  public function resolve(mixed $value, mixed $initialValue): mixed
  {
    if (!is_object($value)) {
      throw new RejectOperations(
        new Error('Cannot call method ' . $this->name . ' on ' . get_debug_type($value) . '.'),
      );
    }

    if (!method_exists($value, $this->name)) {
      // `__call` magic dispatch may still answer the name. Try invoking
      // and trap a BadMethodCallException-style failure.
      if (!method_exists($value, '__call')) {
        throw new RejectOperations(
          new Error('Method ' . $this->name . ' not found on ' . $value::class . '.'),
        );
      }
    }

    try {
      $merged = [...$this->args, ...$this->kwargs];

      return $value->{$this->name}(...$merged);
    } catch (Error $e) {
      throw new RejectOperations($e);
    }
  }
}
