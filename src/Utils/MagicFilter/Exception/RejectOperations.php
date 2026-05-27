<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter\Exception;

use Throwable;

/**
 * Internal flow-control exception: raised by an operation to indicate it
 * cannot resolve against the current value (missing attribute, key error,
 * type mismatch, cast failure, …). Caught by `MagicFilter::_resolve`
 * which then marks the remaining non-`important` operations as rejected
 * and threads `null` through the chain until either an `important`
 * operation runs or the chain terminates.
 *
 * Mirrors upstream `magic_filter.exceptions.RejectOperations`
 * (`magic_filter/exceptions.py:18-19`).
 *
 * The wrapped `Throwable` is the underlying cause (e.g. the
 * `AttributeError` from a missing field). Upstream uses `raise … from e`;
 * PHP achieves the same via the third `$previous` argument to
 * `Exception::__construct`.
 */
final class RejectOperations extends MagicFilterException
{
  public function __construct(?Throwable $previous = null)
  {
    parent::__construct(
      $previous !== null ? $previous->getMessage() : '',
      0,
      $previous,
    );
  }
}
