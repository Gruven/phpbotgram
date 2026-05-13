<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter\Operation;

use Error;
use Gruven\PhpBotGram\Utils\MagicFilter\Exception\RejectOperations;

/**
 * Invoke the running value as a callable: `F->text->lower()` resolves to
 * `$value->lower()` IF `lower` returned a callable from the previous step
 * (typical chain: `F->text->lower` reads attribute, `F->text->lower()`
 * then calls it).
 *
 * In PHP the typical Telegram-data shape doesn't have bound methods on
 * value objects, so the more common use of this operation is to support
 * user-supplied callable values stored in maps: `F->callback($arg1, $arg2)`
 * where `callback` resolves to a `Closure`. We accept any value PHP can
 * call via `is_callable($value)`.
 *
 * Mirrors upstream `magic_filter.operations.call.CallOperation`
 * (`magic_filter/operations/call.py`). Upstream rejects when the value
 * isn't callable; we do the same via `RejectOperations`.
 *
 * Named-arguments support: PHP supports named arguments natively. We
 * store them as a `string => mixed` map and unpack via `$value(...$args)`
 * — argument names collide with positional `$args` to match `$args, kwargs`
 * upstream behaviour exactly. The unpack PHP syntax is `$value(...$args)`,
 * where `$args` is an array with both numeric and string keys.
 */
final class CallOperation extends BaseOperation
{
  /**
   * @param list<mixed> $args
   * @param array<string, mixed> $kwargs
   */
  public function __construct(
    public readonly array $args,
    public readonly array $kwargs,
  ) {}

  public function resolve(mixed $value, mixed $initialValue): mixed
  {
    if (!is_callable($value)) {
      throw new RejectOperations(
        new Error('Value of type ' . get_debug_type($value) . ' is not callable.'),
      );
    }

    // `[...$this->args, ...$this->kwargs]` produces a single array PHP can
    // unpack with `(...)` — numeric entries land as positional arguments
    // and string entries as named arguments. PHP 8.1+ supports this.
    return $value(...[...$this->args, ...$this->kwargs]);
  }
}
